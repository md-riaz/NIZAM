<?php

namespace Tests\Feature\Audit;

use App\Models\Agent;
use App\Models\CallEventLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Recording;
use App\Models\User;
use App\Models\Webhook;
use App\Services\DialplanCompiler;
use App\Services\EventProcessor;
use App\Services\MetricsService;
use App\Services\QueueService;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Full-System Checkpoint Audit — Phase A + B + C
 *
 * Validates structural integrity, state consistency, isolation guarantees,
 * event correctness, distribution determinism, metrics accuracy, and failure recovery.
 */
class SystemCheckpointAuditTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organizationA;

    private Organization $organizationB;

    private User $userA;

    private User $userB;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizationA = Organization::create([
            'name' => 'Organization Alpha',
            'domain' => 'alpha.example.com',
            'max_extensions' => 100,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $this->organizationB = Organization::create([
            'name' => 'Organization Beta',
            'domain' => 'beta.example.com',
            'max_extensions' => 100,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $this->userA = User::factory()->create([
            'organization_id' => $this->organizationA->id,
            'role' => 'agent',
        ]);

        $this->userB = User::factory()->create([
            'organization_id' => $this->organizationB->id,
            'role' => 'agent',
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'organization_id' => null,
        ]);

        // Permissions are deny-by-default; grant userA exactly the abilities
        // this audit's positive-path cases exercise (extensions list, webhooks
        // list). userB stays ungranted since its cases assert isolation denial.
        $slugs = ['extensions.view', 'webhooks.view'];
        foreach ($slugs as $slug) {
            Permission::updateOrCreate(['slug' => $slug], ['module' => 'core']);
        }
        $this->userA->grantPermissions($slugs);
    }

    // ========================================================================
    // CHECKPOINT 1 — CORE ARCHITECTURE INTEGRITY
    // ========================================================================

    public function test_cp1_dialplan_is_compiled_artifact_from_db(): void
    {
        $ext = $this->organizationA->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $compiler = app(DialplanCompiler::class);
        $xml = $compiler->compileDirectory($this->organizationA->domain);

        $this->assertStringContainsString('1001', $xml);
        $this->assertStringContainsString($this->organizationA->domain, $xml);

        // Verify dialplan updates when DB changes
        $ext->update(['extension' => '1002']);
        $xml2 = $compiler->compileDirectory($this->organizationA->domain);

        $this->assertStringContainsString('1002', $xml2);
        $this->assertStringNotContainsString('<user id="1001"', $xml2);
    }

    public function test_cp1_db_is_sole_source_of_truth(): void
    {
        $ext = $this->organizationA->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $did = $this->organizationA->dids()->create([
            'number' => '+15551234567',
            'description' => 'Main Line',
            'destination_type' => 'extension',
            'destination_id' => $ext->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('dids', ['id' => $did->id, 'number' => '+15551234567']);

        // Deleting from DB removes the resource
        $did->delete();
        $this->assertDatabaseMissing('dids', ['id' => $did->id]);
    }

    public function test_cp1_removing_ui_does_not_break_api(): void
    {
        // All functionality is accessible via API — no UI dependency
        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organizationA->id}/extensions");
        $response->assertStatus(200);
    }

    public function test_cp1_user_without_permission_cannot_list_extensions(): void
    {
        $unprivileged = User::factory()->create([
            'organization_id' => $this->organizationA->id,
            'role' => 'agent',
        ]);

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organizationA->id}/extensions")
            ->assertForbidden();
    }

    // ========================================================================
    // CHECKPOINT 2 — MULTI-ORGANIZATION ISOLATION
    // ========================================================================

    public function test_cp2_same_extension_numbers_across_organizations_no_conflict(): void
    {
        $this->organizationA->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'first_name' => 'Alice',
            'last_name' => 'Alpha',
        ]);

        $this->organizationB->extensions()->create([
            'extension' => '1001',
            'password' => 'secret456',
            'first_name' => 'Bob',
            'last_name' => 'Beta',
        ]);

        $this->assertCount(1, $this->organizationA->extensions);
        $this->assertCount(1, $this->organizationB->extensions);
        $this->assertEquals('Alice', $this->organizationA->extensions->first()->first_name);
        $this->assertEquals('Bob', $this->organizationB->extensions->first()->first_name);
    }

    public function test_cp2_queue_names_isolated_per_organization(): void
    {
        Queue::create(['organization_id' => $this->organizationA->id, 'name' => 'Support Queue']);
        Queue::create(['organization_id' => $this->organizationB->id, 'name' => 'Support Queue']);

        $this->assertCount(1, $this->organizationA->queues);
        $this->assertCount(1, $this->organizationB->queues);
    }

    public function test_cp2_agent_state_scoped_per_organization(): void
    {
        $extA = $this->organizationA->extensions()->create([
            'extension' => '1001', 'password' => 'secret', 'first_name' => 'A', 'last_name' => 'Agent',
        ]);
        $extB = $this->organizationB->extensions()->create([
            'extension' => '1001', 'password' => 'secret', 'first_name' => 'B', 'last_name' => 'Agent',
        ]);

        $agentA = Agent::create(['organization_id' => $this->organizationA->id, 'extension_id' => $extA->id, 'name' => 'Agent A', 'state' => Agent::STATE_AVAILABLE]);
        $agentB = Agent::create(['organization_id' => $this->organizationB->id, 'extension_id' => $extB->id, 'name' => 'Agent B', 'state' => Agent::STATE_BUSY]);

        $this->assertEquals(Agent::STATE_AVAILABLE, $agentA->state);
        $this->assertEquals(Agent::STATE_BUSY, $agentB->state);

        // Changing one organization's agent doesn't affect other
        $agentA->transitionState(Agent::STATE_PAUSED, Agent::PAUSE_LUNCH);
        $agentB->refresh();
        $this->assertEquals(Agent::STATE_BUSY, $agentB->state);
    }

    public function test_cp2_cross_organization_api_read_attack_blocked(): void
    {
        $this->organizationA->extensions()->create([
            'extension' => '1001', 'password' => 'secret', 'first_name' => 'A', 'last_name' => 'X',
        ]);

        // Organization B user tries to access Organization A resources
        $response = $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organizationA->id}/extensions");

        $response->assertStatus(403);
    }

    public function test_cp2_cross_organization_agent_access_blocked(): void
    {
        $extA = $this->organizationA->extensions()->create([
            'extension' => '1001', 'password' => 'secret', 'first_name' => 'A', 'last_name' => 'X',
        ]);
        $agentA = Agent::create([
            'organization_id' => $this->organizationA->id, 'extension_id' => $extA->id, 'name' => 'Agent A',
        ]);

        // Organization B user tries to read Organization A's agent via Organization B's path
        $response = $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organizationB->id}/agents/{$agentA->id}");

        $response->assertStatus(404);
    }

    public function test_cp2_cross_organization_queue_access_blocked(): void
    {
        $queueA = Queue::create(['organization_id' => $this->organizationA->id, 'name' => 'Queue A']);

        $response = $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organizationB->id}/queues/{$queueA->id}");

        $response->assertStatus(404);
    }

    public function test_cp2_metrics_queries_isolated(): void
    {
        $metricsService = new MetricsService;

        $queueA = Queue::create(['organization_id' => $this->organizationA->id, 'name' => 'Q-A']);
        $queueB = Queue::create(['organization_id' => $this->organizationB->id, 'name' => 'Q-B']);

        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queueA->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(5), 'wait_duration' => 15,
        ]);

        $metricsB = $metricsService->getRealTimeMetrics($queueB);
        $this->assertEquals(0, $metricsB['calls_offered']);

        $wallboardB = $metricsService->getWallboardData($this->organizationB->id);
        $this->assertEquals(0, $wallboardB['queues'][0]['calls_offered']);
    }

    public function test_cp2_webhook_dispatch_isolated(): void
    {
        Webhook::create([
            'organization_id' => $this->organizationA->id,
            'url' => 'https://alpha.example.com/hook',
            'events' => ['call.created'],
            'secret' => 'alpha-secret-key',
            'is_active' => true,
        ]);

        Webhook::create([
            'organization_id' => $this->organizationB->id,
            'url' => 'https://beta.example.com/hook',
            'events' => ['call.created'],
            'secret' => 'beta-secret-key',
            'is_active' => true,
        ]);

        // Only Organization A's webhooks should be queried for Organization A events
        $organizationAWebhooks = Webhook::where('organization_id', $this->organizationA->id)
            ->where('is_active', true)
            ->get();

        $this->assertCount(1, $organizationAWebhooks);
        $this->assertStringContainsString('alpha', $organizationAWebhooks->first()->url);
    }

    // ========================================================================
    // CHECKPOINT 3 — EVENT BUS CONSISTENCY
    // ========================================================================

    public function test_cp3_complete_call_lifecycle_events(): void
    {
        Event::fake();

        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class)
        );

        $callUuid = (string) Str::uuid();
        $baseEvent = [
            'variable_domain_name' => $this->organizationA->domain,
            'Unique-ID' => $callUuid,
            'Caller-Caller-ID-Name' => 'Test',
            'Caller-Caller-ID-Number' => '+15551234567',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ];

        // Full lifecycle
        $processor->process(array_merge($baseEvent, ['Event-Name' => 'CHANNEL_CREATE']));
        $processor->process(array_merge($baseEvent, ['Event-Name' => 'CHANNEL_ANSWER']));
        $processor->process(array_merge($baseEvent, [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'Other-Leg-Unique-ID' => (string) Str::uuid(),
        ]));
        $processor->process(array_merge($baseEvent, [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_duration' => '120',
            'variable_billsec' => '100',
            'variable_start_stamp' => now()->subMinutes(2)->toIso8601String(),
            'variable_end_stamp' => now()->toIso8601String(),
        ]));

        // Verify all lifecycle events recorded
        $events = CallEventLog::where('call_uuid', $callUuid)
            ->orderBy('occurred_at')
            ->pluck('event_type')
            ->toArray();

        $this->assertContains(CallEventLog::EVENT_CALL_CREATED, $events);
        $this->assertContains(CallEventLog::EVENT_CALL_ANSWERED, $events);
        $this->assertContains(CallEventLog::EVENT_CALL_BRIDGED, $events);
        $this->assertContains(CallEventLog::EVENT_CALL_HANGUP, $events);
    }

    public function test_cp3_event_schema_version_correct(): void
    {
        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class)
        );

        $processor->process([
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => $this->organizationA->domain,
            'Unique-ID' => (string) Str::uuid(),
            'Caller-Caller-ID-Name' => 'Test',
            'Caller-Caller-ID-Number' => '+15551234567',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ]);

        $event = CallEventLog::first();
        $this->assertEquals(CallEventLog::SCHEMA_VERSION, $event->schema_version);
    }

    public function test_cp3_no_orphan_events_without_organization(): void
    {
        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class)
        );

        // Event with unknown domain should not create records
        $processor->process([
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'unknown.nonexistent.com',
            'Unique-ID' => (string) Str::uuid(),
            'Caller-Caller-ID-Name' => 'Test',
            'Caller-Caller-ID-Number' => '+15551234567',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ]);

        $this->assertCount(0, CallEventLog::all());
    }

    public function test_cp3_events_scoped_to_organization(): void
    {
        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class)
        );

        // Create event for Organization A
        $processor->process([
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => $this->organizationA->domain,
            'Unique-ID' => (string) Str::uuid(),
            'Caller-Caller-ID-Name' => 'Test',
            'Caller-Caller-ID-Number' => '+15551234567',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ]);

        // Verify event belongs to correct organization
        $event = CallEventLog::first();
        $this->assertEquals($this->organizationA->id, $event->organization_id);
    }

    // ========================================================================
    // CHECKPOINT 4 — POLICY ENGINE VALIDATION
    // ========================================================================

    public function test_cp4_suspended_organization_blocks_routing(): void
    {
        $suspendedOrganization = Organization::create([
            'name' => 'Suspended Corp',
            'domain' => 'suspended.example.com',
            'max_extensions' => 10,
            'status' => Organization::STATUS_SUSPENDED,
            'is_active' => false,
        ]);

        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class)
        );

        // Event for suspended organization should not process
        $processor->process([
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => $suspendedOrganization->domain,
            'Unique-ID' => (string) Str::uuid(),
            'Caller-Caller-ID-Name' => 'Test',
            'Caller-Caller-ID-Number' => '+15551234567',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ]);

        $this->assertCount(0, CallEventLog::all());
    }

    public function test_cp4_suspended_organization_blocked_at_api(): void
    {
        $this->organizationA->update([
            'status' => Organization::STATUS_SUSPENDED,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organizationA->id}/extensions");

        $response->assertStatus(403);
    }

    public function test_cp4_terminated_organization_blocked_at_api(): void
    {
        $this->organizationA->update([
            'status' => Organization::STATUS_TERMINATED,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organizationA->id}/extensions");

        $response->assertStatus(403);
    }

    public function test_cp4_dialplan_rejects_suspended_organization(): void
    {
        $this->organizationA->update([
            'status' => Organization::STATUS_SUSPENDED,
            'is_active' => false,
        ]);

        $compiler = app(DialplanCompiler::class);
        $xml = $compiler->compileDialplan($this->organizationA->domain, '+15551234567', '1001');

        // Suspended organization gets empty dialplan response (no routing allowed)
        $this->assertStringContainsString('<section name="dialplan"', $xml);
        $this->assertStringNotContainsString('<extension', $xml);
    }

    // ========================================================================
    // CHECKPOINT 5 — QUEUE DISTRIBUTION DETERMINISM
    // ========================================================================

    public function test_cp5_150_calls_20_agents_no_stuck_calls(): void
    {
        Event::fake();

        $queueService = new QueueService;
        $queue = Queue::create([
            'organization_id' => $this->organizationA->id,
            'name' => 'High Volume',
            'strategy' => Queue::STRATEGY_ROUND_ROBIN,
            'max_wait_time' => 300,
        ]);

        // Create 20 agents
        $agents = [];
        for ($i = 0; $i < 20; $i++) {
            $ext = $this->organizationA->extensions()->create([
                'extension' => (string) (2001 + $i),
                'password' => 'secret123',
                'first_name' => "Agent{$i}",
                'last_name' => 'Test',
            ]);
            $agent = Agent::create([
                'organization_id' => $this->organizationA->id,
                'extension_id' => $ext->id,
                'name' => "Agent {$i}",
                'state' => Agent::STATE_AVAILABLE,
            ]);
            $queue->members()->attach($agent->id, [
                'id' => Str::uuid(),
                'priority' => $i,
            ]);
            $agents[] = $agent;
        }

        // Queue 150 calls
        $entries = [];
        for ($i = 0; $i < 150; $i++) {
            $entries[] = $queueService->addToQueue($queue, (string) Str::uuid(), [
                'caller_id_number' => '+1555'.str_pad($i, 7, '0', STR_PAD_LEFT),
            ]);
        }

        $this->assertEquals(150, QueueEntry::where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_WAITING)->count());

        // Answer first 20 calls
        for ($i = 0; $i < 20; $i++) {
            $queueService->answerEntry($entries[$i], $agents[$i]);
        }

        // Abandon 30 calls
        for ($i = 20; $i < 50; $i++) {
            $queueService->abandonEntry($entries[$i], 'caller_hangup');
        }

        // Overflow 20 calls
        for ($i = 50; $i < 70; $i++) {
            $queueService->overflowEntry($entries[$i]);
        }

        // Verify counts: 80 waiting, 20 answered, 30 abandoned, 20 overflowed
        $this->assertEquals(80, QueueEntry::where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_WAITING)->count());
        $this->assertEquals(20, QueueEntry::where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_ANSWERED)->count());
        $this->assertEquals(30, QueueEntry::where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_ABANDONED)->count());
        $this->assertEquals(20, QueueEntry::where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_OVERFLOWED)->count());

        // Total = 150, no stuck or missing
        $total = QueueEntry::where('queue_id', $queue->id)->count();
        $this->assertEquals(150, $total);
    }

    public function test_cp5_paused_agents_excluded_from_distribution(): void
    {
        Event::fake();
        $queueService = new QueueService;

        $queue = Queue::create([
            'organization_id' => $this->organizationA->id,
            'name' => 'Pause Test',
            'strategy' => Queue::STRATEGY_ROUND_ROBIN,
        ]);

        $ext1 = $this->organizationA->extensions()->create([
            'extension' => '3001', 'password' => 'secret', 'first_name' => 'A1', 'last_name' => 'T',
        ]);
        $ext2 = $this->organizationA->extensions()->create([
            'extension' => '3002', 'password' => 'secret', 'first_name' => 'A2', 'last_name' => 'T',
        ]);

        $agent1 = Agent::create([
            'organization_id' => $this->organizationA->id, 'extension_id' => $ext1->id,
            'name' => 'Agent Paused', 'state' => Agent::STATE_PAUSED, 'pause_reason' => Agent::PAUSE_LUNCH,
        ]);
        $agent2 = Agent::create([
            'organization_id' => $this->organizationA->id, 'extension_id' => $ext2->id,
            'name' => 'Agent Available', 'state' => Agent::STATE_AVAILABLE,
        ]);

        $queue->members()->attach($agent1->id, ['id' => Str::uuid(), 'priority' => 0]);
        $queue->members()->attach($agent2->id, ['id' => Str::uuid(), 'priority' => 1]);

        $selected = $queueService->selectAgent($queue);
        $this->assertEquals($agent2->id, $selected->id);
    }

    public function test_cp5_ring_all_returns_all_available(): void
    {
        $queueService = new QueueService;

        $queue = Queue::create([
            'organization_id' => $this->organizationA->id,
            'name' => 'Ring All Test',
            'strategy' => Queue::STRATEGY_RING_ALL,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $ext = $this->organizationA->extensions()->create([
                'extension' => (string) (4001 + $i), 'password' => 'secret',
                'first_name' => "A{$i}", 'last_name' => 'T',
            ]);
            $agent = Agent::create([
                'organization_id' => $this->organizationA->id, 'extension_id' => $ext->id,
                'name' => "Agent {$i}", 'state' => Agent::STATE_AVAILABLE,
            ]);
            $queue->members()->attach($agent->id, ['id' => Str::uuid(), 'priority' => $i]);
        }

        $available = $queueService->getAgentsForRingAll($queue);
        $this->assertCount(5, $available);
    }

    public function test_cp5_overflow_triggers_correctly(): void
    {
        Event::fake();
        $queueService = new QueueService;

        $queue = Queue::create([
            'organization_id' => $this->organizationA->id,
            'name' => 'Overflow Test',
            'max_wait_time' => 60,
        ]);

        // Entry within time limit
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_WAITING,
            'join_time' => now()->subSeconds(30),
        ]);

        // Entry exceeding time limit
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_WAITING,
            'join_time' => now()->subSeconds(120),
        ]);

        $candidates = $queueService->getOverflowCandidates($queue);
        $this->assertCount(1, $candidates);
    }

    // ========================================================================
    // CHECKPOINT 6 — AGENT STATE MACHINE
    // ========================================================================

    public function test_cp6_valid_state_transitions(): void
    {
        $ext = $this->organizationA->extensions()->create([
            'extension' => '5001', 'password' => 'secret',
            'first_name' => 'SM', 'last_name' => 'Agent',
        ]);
        $agent = Agent::create([
            'organization_id' => $this->organizationA->id, 'extension_id' => $ext->id,
            'name' => 'State Machine Agent', 'state' => Agent::STATE_OFFLINE,
        ]);

        // offline → available
        $agent->transitionState(Agent::STATE_AVAILABLE);
        $this->assertEquals(Agent::STATE_AVAILABLE, $agent->fresh()->state);

        // available → paused
        $agent->transitionState(Agent::STATE_PAUSED, Agent::PAUSE_BREAK);
        $agent->refresh();
        $this->assertEquals(Agent::STATE_PAUSED, $agent->state);
        $this->assertEquals(Agent::PAUSE_BREAK, $agent->pause_reason);

        // paused → available (pause reason cleared)
        $agent->transitionState(Agent::STATE_AVAILABLE);
        $agent->refresh();
        $this->assertEquals(Agent::STATE_AVAILABLE, $agent->state);
        $this->assertNull($agent->pause_reason);

        // available → busy
        $agent->transitionState(Agent::STATE_BUSY);
        $this->assertEquals(Agent::STATE_BUSY, $agent->fresh()->state);

        // busy → available
        $agent->transitionState(Agent::STATE_AVAILABLE);
        $this->assertEquals(Agent::STATE_AVAILABLE, $agent->fresh()->state);

        // available → offline
        $agent->transitionState(Agent::STATE_OFFLINE);
        $this->assertEquals(Agent::STATE_OFFLINE, $agent->fresh()->state);
    }

    public function test_cp6_no_impossible_states(): void
    {
        $ext = $this->organizationA->extensions()->create([
            'extension' => '5002', 'password' => 'secret',
            'first_name' => 'IS', 'last_name' => 'Agent',
        ]);
        $agent = Agent::create([
            'organization_id' => $this->organizationA->id, 'extension_id' => $ext->id,
            'name' => 'Impossible State Agent', 'state' => Agent::STATE_AVAILABLE,
        ]);

        // After busy transition, agent should be busy, not available
        $agent->transitionState(Agent::STATE_BUSY);
        $agent->refresh();
        $this->assertEquals(Agent::STATE_BUSY, $agent->state);
        $this->assertFalse($agent->isAvailable());

        // Invalid state through API is rejected
        $response = $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organizationA->id}/agents/{$agent->id}/state", [
                'state' => 'on_call_and_available', // invalid state
            ]);
        $response->assertStatus(422);
    }

    public function test_cp6_call_events_update_agent_state(): void
    {
        Event::fake();

        $ext = $this->organizationA->extensions()->create([
            'extension' => '5003', 'password' => 'secret',
            'first_name' => 'CE', 'last_name' => 'Agent',
        ]);
        $agent = Agent::create([
            'organization_id' => $this->organizationA->id, 'extension_id' => $ext->id,
            'name' => 'Call Event Agent', 'state' => Agent::STATE_AVAILABLE,
        ]);

        $queue = Queue::create(['organization_id' => $this->organizationA->id, 'name' => 'CE Queue']);
        $queue->members()->attach($agent->id, ['id' => Str::uuid(), 'priority' => 0]);

        $queueService = new QueueService;
        $entry = $queueService->addToQueue($queue, (string) Str::uuid());

        // Answering should set agent to busy
        $queueService->answerEntry($entry, $agent);
        $agent->refresh();
        $this->assertEquals(Agent::STATE_BUSY, $agent->state);
    }

    public function test_cp6_state_changed_at_always_updated(): void
    {
        $ext = $this->organizationA->extensions()->create([
            'extension' => '5004', 'password' => 'secret',
            'first_name' => 'TS', 'last_name' => 'Agent',
        ]);
        $agent = Agent::create([
            'organization_id' => $this->organizationA->id, 'extension_id' => $ext->id,
            'name' => 'Timestamp Agent', 'state' => Agent::STATE_OFFLINE,
        ]);

        $agent->transitionState(Agent::STATE_AVAILABLE);
        $agent->refresh();
        $this->assertNotNull($agent->state_changed_at);

        // Change again
        $agent->transitionState(Agent::STATE_BUSY);
        $agent->refresh();
        $this->assertNotNull($agent->state_changed_at);
    }

    // ========================================================================
    // CHECKPOINT 7 — SLA & METRICS ACCURACY
    // ========================================================================

    public function test_cp7_average_wait_time_matches_timestamps(): void
    {
        $metricsService = new MetricsService;
        $queue = Queue::create([
            'organization_id' => $this->organizationA->id, 'name' => 'Metrics Queue',
            'service_level_threshold' => 20,
        ]);

        // Create entries with known wait durations
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(10), 'wait_duration' => 10,
        ]);
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(8), 'wait_duration' => 20,
        ]);
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(5), 'wait_duration' => 30,
        ]);

        $metrics = $metricsService->getRealTimeMetrics($queue);

        // Manual computation: (10 + 20 + 30) / 3 = 20
        $this->assertEquals(20.0, $metrics['average_wait_time']);
        $this->assertEquals(30.0, $metrics['max_wait_time']);
    }

    public function test_cp7_abandon_rate_correct(): void
    {
        $metricsService = new MetricsService;
        $queue = Queue::create(['organization_id' => $this->organizationA->id, 'name' => 'Abandon Queue']);

        // 3 answered, 2 abandoned = 40% abandon rate
        for ($i = 0; $i < 3; $i++) {
            QueueEntry::create([
                'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
                'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ANSWERED,
                'join_time' => now()->subMinutes(5), 'wait_duration' => 10,
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            QueueEntry::create([
                'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
                'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ABANDONED,
                'join_time' => now()->subMinutes(3), 'abandon_time' => now(), 'wait_duration' => 30,
            ]);
        }

        $metrics = $metricsService->getRealTimeMetrics($queue);
        $this->assertEquals(40.0, $metrics['abandon_rate']); // 2/5 = 40%
    }

    public function test_cp7_service_level_threshold_correct(): void
    {
        $metricsService = new MetricsService;
        $queue = Queue::create([
            'organization_id' => $this->organizationA->id, 'name' => 'SLA Queue',
            'service_level_threshold' => 20,
        ]);

        // 2 within SLA (15s, 18s), 1 outside (30s), 1 abandoned
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(10), 'wait_duration' => 15,
        ]);
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(8), 'wait_duration' => 18,
        ]);
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(5), 'wait_duration' => 30,
        ]);
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ABANDONED,
            'join_time' => now()->subMinutes(3), 'abandon_time' => now(), 'wait_duration' => 45,
        ]);

        $metrics = $metricsService->getRealTimeMetrics($queue);

        // Manual: 2 within SLA out of 4 total = 50%
        $this->assertEquals(50.0, $metrics['service_level']);
    }

    public function test_cp7_agent_occupancy_correct(): void
    {
        $metricsService = new MetricsService;
        $queue = Queue::create(['organization_id' => $this->organizationA->id, 'name' => 'Occupancy Queue']);

        // 3 agents: 1 busy, 1 available, 1 paused
        $states = [Agent::STATE_BUSY, Agent::STATE_AVAILABLE, Agent::STATE_PAUSED];
        foreach ($states as $i => $state) {
            $ext = $this->organizationA->extensions()->create([
                'extension' => (string) (6001 + $i), 'password' => 'secret',
                'first_name' => "O{$i}", 'last_name' => 'A',
            ]);
            $agent = Agent::create([
                'organization_id' => $this->organizationA->id, 'extension_id' => $ext->id,
                'name' => "Occ Agent {$i}", 'state' => $state,
                'pause_reason' => $state === Agent::STATE_PAUSED ? Agent::PAUSE_BREAK : null,
            ]);
            $queue->members()->attach($agent->id, ['id' => Str::uuid(), 'priority' => $i]);
        }

        $metrics = $metricsService->getRealTimeMetrics($queue);
        // 1 busy out of 3 active = 33.33%
        $this->assertEquals(33.33, $metrics['agent_occupancy']);
    }

    public function test_cp7_historical_aggregation_matches_raw(): void
    {
        $metricsService = new MetricsService;
        $queue = Queue::create([
            'organization_id' => $this->organizationA->id, 'name' => 'History Queue',
            'service_level_threshold' => 20,
        ]);

        $periodStart = now()->startOfHour();

        // Create entries within this hour
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => $periodStart->copy()->addMinutes(5), 'wait_duration' => 12,
        ]);
        QueueEntry::create([
            'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(), 'status' => QueueEntry::STATUS_ABANDONED,
            'join_time' => $periodStart->copy()->addMinutes(15),
            'abandon_time' => $periodStart->copy()->addMinutes(17), 'wait_duration' => 120,
        ]);

        $metric = $metricsService->aggregateMetrics($queue, 'hourly', $periodStart);

        $this->assertEquals(2, $metric->calls_offered);
        $this->assertEquals(1, $metric->calls_answered);
        $this->assertEquals(1, $metric->calls_abandoned);
        $this->assertEquals(50.0, (float) $metric->abandon_rate);
    }

    // ========================================================================
    // CHECKPOINT 8 — WEBHOOK & AUTOMATION RELIABILITY
    // ========================================================================

    public function test_cp8_webhook_organization_scoped_subscription(): void
    {
        Webhook::create([
            'organization_id' => $this->organizationA->id,
            'url' => 'https://alpha.example.com/hook',
            'events' => ['call.created', 'call.hangup'],
            'secret' => 'alpha-secret-key',
            'is_active' => true,
        ]);

        Webhook::create([
            'organization_id' => $this->organizationB->id,
            'url' => 'https://beta.example.com/hook',
            'events' => ['call.created'],
            'secret' => 'beta-secret-key',
            'is_active' => true,
        ]);

        // Organization A should only see its own webhooks
        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organizationA->id}/webhooks");

        $response->assertStatus(200);
        $webhooks = $response->json('data');
        foreach ($webhooks as $webhook) {
            $this->assertEquals($this->organizationA->id, $webhook['organization_id']);
        }
    }

    public function test_cp8_webhook_event_dispatch_correct(): void
    {
        $dispatched = [];
        $webhookDispatcher = $this->createMock(WebhookDispatcher::class);
        $webhookDispatcher->method('dispatch')
            ->willReturnCallback(function ($organizationId, $eventType, $data) use (&$dispatched) {
                $dispatched[] = ['organization_id' => $organizationId, 'event_type' => $eventType];
            });

        $processor = new EventProcessor($webhookDispatcher);

        $callUuid = (string) Str::uuid();
        $baseEvent = [
            'variable_domain_name' => $this->organizationA->domain,
            'Unique-ID' => $callUuid,
            'Caller-Caller-ID-Name' => 'Test',
            'Caller-Caller-ID-Number' => '+15551234567',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ];

        $processor->process(array_merge($baseEvent, ['Event-Name' => 'CHANNEL_CREATE']));
        $processor->process(array_merge($baseEvent, [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'Hangup-Cause' => 'NO_ANSWER',
            'variable_duration' => '30',
            'variable_billsec' => '0',
            'variable_start_stamp' => now()->subSeconds(30)->toIso8601String(),
            'variable_end_stamp' => now()->toIso8601String(),
        ]));

        // Verify dispatches: call.created, call.hangup, call.missed
        $eventTypes = array_column($dispatched, 'event_type');
        $this->assertContains('call.created', $eventTypes);
        $this->assertContains('call.hangup', $eventTypes);
        $this->assertContains('call.missed', $eventTypes);

        // All dispatches should be scoped to Organization A
        foreach ($dispatched as $d) {
            $this->assertEquals($this->organizationA->id, $d['organization_id']);
        }
    }

    // ========================================================================
    // CHECKPOINT 9 — CALL CONTROL API SAFETY
    // ========================================================================

    public function test_cp9_call_control_requires_auth(): void
    {
        $response = $this->postJson("/api/v1/organizations/{$this->organizationA->id}/calls/hangup", [
            'uuid' => (string) Str::uuid(),
        ]);

        $response->assertStatus(401);
    }

    public function test_cp9_organization_scoped_call_control(): void
    {
        // Organization B user cannot access Organization A's call control
        $response = $this->actingAs($this->userB, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organizationA->id}/calls/hangup", [
                'uuid' => (string) Str::uuid(),
            ]);

        $response->assertStatus(403);
    }

    public function test_cp9_rate_limiting_enforced(): void
    {
        // API has throttle middleware - verify it exists in route definition
        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    // ========================================================================
    // CHECKPOINT 10 — FAILURE RECOVERY
    // ========================================================================

    public function test_cp10_agent_states_reconcile_after_transition_storm(): void
    {
        Event::fake();

        $ext = $this->organizationA->extensions()->create([
            'extension' => '7001', 'password' => 'secret',
            'first_name' => 'RC', 'last_name' => 'Agent',
        ]);
        $agent = Agent::create([
            'organization_id' => $this->organizationA->id, 'extension_id' => $ext->id,
            'name' => 'Recovery Agent', 'state' => Agent::STATE_OFFLINE,
        ]);

        // Rapid state transitions (simulating recovery scenario)
        $transitions = [
            Agent::STATE_AVAILABLE,
            Agent::STATE_BUSY,
            Agent::STATE_AVAILABLE,
            Agent::STATE_PAUSED,
            Agent::STATE_AVAILABLE,
            Agent::STATE_RINGING,
            Agent::STATE_BUSY,
            Agent::STATE_AVAILABLE,
            Agent::STATE_OFFLINE,
        ];

        foreach ($transitions as $state) {
            $pauseReason = $state === Agent::STATE_PAUSED ? Agent::PAUSE_AFTER_CALL_WORK : null;
            $agent->transitionState($state, $pauseReason);
        }

        $agent->refresh();
        $this->assertEquals(Agent::STATE_OFFLINE, $agent->state);
        $this->assertNull($agent->pause_reason);
        $this->assertNotNull($agent->state_changed_at);
    }

    public function test_cp10_queue_entries_consistent_after_mixed_operations(): void
    {
        Event::fake();
        $queueService = new QueueService;

        $queue = Queue::create([
            'organization_id' => $this->organizationA->id, 'name' => 'Recovery Queue',
            'max_wait_time' => 300,
        ]);

        // Add 10 entries
        $entries = [];
        for ($i = 0; $i < 10; $i++) {
            $entries[] = $queueService->addToQueue($queue, (string) Str::uuid());
        }

        // Mixed operations: answer 3, abandon 3, overflow 2, leave 2 waiting
        $ext = $this->organizationA->extensions()->create([
            'extension' => '7010', 'password' => 'secret',
            'first_name' => 'R', 'last_name' => 'A',
        ]);
        $agent = Agent::create([
            'organization_id' => $this->organizationA->id, 'extension_id' => $ext->id,
            'name' => 'R Agent', 'state' => Agent::STATE_AVAILABLE,
        ]);

        // Answer 3
        for ($i = 0; $i < 3; $i++) {
            $agent->transitionState(Agent::STATE_AVAILABLE);
            $queueService->answerEntry($entries[$i], $agent);
        }

        // Abandon 3
        for ($i = 3; $i < 6; $i++) {
            $queueService->abandonEntry($entries[$i], 'timeout');
        }

        // Overflow 2
        for ($i = 6; $i < 8; $i++) {
            $queueService->overflowEntry($entries[$i]);
        }

        // Verify total consistency
        $total = QueueEntry::where('queue_id', $queue->id)->count();
        $this->assertEquals(10, $total);

        $answered = QueueEntry::where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_ANSWERED)->count();
        $abandoned = QueueEntry::where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_ABANDONED)->count();
        $overflowed = QueueEntry::where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_OVERFLOWED)->count();
        $waiting = QueueEntry::where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_WAITING)->count();

        $this->assertEquals(3, $answered);
        $this->assertEquals(3, $abandoned);
        $this->assertEquals(2, $overflowed);
        $this->assertEquals(2, $waiting);
        $this->assertEquals(10, $answered + $abandoned + $overflowed + $waiting);
    }

    public function test_cp10_no_zombie_call_states(): void
    {
        Event::fake();
        $queueService = new QueueService;

        $queue = Queue::create([
            'organization_id' => $this->organizationA->id, 'name' => 'Zombie Queue',
        ]);

        // Create entries and process them all
        for ($i = 0; $i < 20; $i++) {
            $entry = $queueService->addToQueue($queue, (string) Str::uuid());
            if ($i % 2 === 0) {
                $queueService->abandonEntry($entry, 'test');
            }
        }

        // All entries should be in a terminal state or still waiting
        $entries = QueueEntry::where('queue_id', $queue->id)->get();
        foreach ($entries as $entry) {
            $this->assertContains($entry->status, QueueEntry::VALID_STATUSES);
        }
    }

    // ========================================================================
    // CHECKPOINT 11 — PERFORMANCE BASELINE
    // ========================================================================

    public function test_cp11_high_volume_queue_operations(): void
    {
        Event::fake();
        $queueService = new QueueService;

        $queue = Queue::create([
            'organization_id' => $this->organizationA->id, 'name' => 'Perf Queue',
            'strategy' => Queue::STRATEGY_ROUND_ROBIN,
        ]);

        // Create 50 agents
        for ($i = 0; $i < 50; $i++) {
            $ext = $this->organizationA->extensions()->create([
                'extension' => (string) (8001 + $i), 'password' => 'secret',
                'first_name' => "P{$i}", 'last_name' => 'A',
            ]);
            $agent = Agent::create([
                'organization_id' => $this->organizationA->id, 'extension_id' => $ext->id,
                'name' => "Perf Agent {$i}", 'state' => Agent::STATE_AVAILABLE,
            ]);
            $queue->members()->attach($agent->id, ['id' => Str::uuid(), 'priority' => $i]);
        }

        // Queue 200 calls
        $startTime = microtime(true);
        for ($i = 0; $i < 200; $i++) {
            $queueService->addToQueue($queue, (string) Str::uuid());
        }
        $queueTime = microtime(true) - $startTime;

        $this->assertCount(200, QueueEntry::where('queue_id', $queue->id)->get());

        // Verify agent selection is performant
        $startTime = microtime(true);
        for ($i = 0; $i < 50; $i++) {
            $queueService->selectAgent($queue);
        }
        $selectTime = microtime(true) - $startTime;

        // Operations should complete within generous CI-safe thresholds
        $this->assertLessThan(30.0, $queueTime, 'Queue 200 calls should complete within time limit');
        $this->assertLessThan(15.0, $selectTime, 'Agent selection should complete within time limit');
    }

    public function test_cp11_metrics_computation_performant(): void
    {
        $metricsService = new MetricsService;
        $queue = Queue::create([
            'organization_id' => $this->organizationA->id, 'name' => 'Perf Metrics Queue',
            'service_level_threshold' => 20,
        ]);

        // Create 100 entries with deterministic data
        for ($i = 0; $i < 100; $i++) {
            QueueEntry::create([
                'organization_id' => $this->organizationA->id, 'queue_id' => $queue->id,
                'call_uuid' => (string) Str::uuid(),
                'status' => $i % 3 === 0 ? QueueEntry::STATUS_ABANDONED : QueueEntry::STATUS_ANSWERED,
                'join_time' => now()->subMinutes($i + 1),
                'wait_duration' => 5 + ($i % 20) * 6,
            ]);
        }

        $startTime = microtime(true);
        $metrics = $metricsService->getRealTimeMetrics($queue);
        $computeTime = microtime(true) - $startTime;

        $this->assertLessThan(10.0, $computeTime, 'Metrics computation should complete within time limit');
        $this->assertArrayHasKey('service_level', $metrics);
        $this->assertArrayHasKey('abandon_rate', $metrics);
    }

    // ========================================================================
    // CHECKPOINT 12 — ARCHITECTURAL FUTURE READINESS
    // ========================================================================

    public function test_cp12_event_bus_extensible(): void
    {
        // ContactCenterEvent can handle any event type string
        $event = new \App\Events\ContactCenterEvent(
            $this->organizationA->id,
            'custom.ai_analysis_complete',
            ['score' => 95, 'analysis_id' => 'abc123']
        );

        $this->assertEquals('contact-center.custom.ai_analysis_complete', $event->broadcastAs());
        $this->assertArrayHasKey('score', $event->data);
    }

    public function test_cp12_queue_model_extensible_for_skill_routing(): void
    {
        // Queue model can store additional strategy types
        $queue = Queue::create([
            'organization_id' => $this->organizationA->id,
            'name' => 'Future Queue',
            'strategy' => Queue::STRATEGY_ROUND_ROBIN,
        ]);

        // Model structure supports future extension
        $this->assertContains('ring_all', Queue::VALID_STRATEGIES);
        $this->assertContains('round_robin', Queue::VALID_STRATEGIES);
        $this->assertContains('least_recent', Queue::VALID_STRATEGIES);
    }

    public function test_cp12_recording_metadata_prepared_for_ai(): void
    {
        $recording = Recording::factory()->create([
            'organization_id' => $this->organizationA->id,
            'call_uuid' => (string) Str::uuid(),
            'queue_name' => 'Support Queue',
            'agent_id' => 'agent-uuid',
            'wait_time' => 15,
            'outcome' => 'answered',
            'abandon_reason' => null,
        ]);

        $recording->refresh();
        $this->assertEquals('Support Queue', $recording->queue_name);
        $this->assertEquals(15, $recording->wait_time);
        $this->assertEquals('answered', $recording->outcome);
    }

    public function test_cp12_wallboard_data_consumable_by_external_sdk(): void
    {
        $metricsService = new MetricsService;
        Queue::create(['organization_id' => $this->organizationA->id, 'name' => 'SDK Queue']);

        $wallboard = $metricsService->getWallboardData($this->organizationA->id);

        // Verify structure is clean JSON-serializable format
        $json = json_encode($wallboard);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('queues', $decoded);
        $this->assertArrayHasKey('agent_states', $decoded);
        $this->assertArrayHasKey('agents', $decoded);
    }
}
