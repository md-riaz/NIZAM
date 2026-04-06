<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Tenant;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    private MetricsService $metricsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsService = new MetricsService;
    }

    private function createSetup(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Corp',
            'domain' => 'test.example.com',
            'slug' => 'test-corp',
            'max_extensions' => 50,
        ]);

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support Queue',
            'strategy' => Queue::STRATEGY_ROUND_ROBIN,
            'max_wait_time' => 300,
            'service_level_threshold' => 20,
        ]);

        return [$tenant, $queue];
    }

    private function createAgentForTenant(Tenant $tenant, string $state = Agent::STATE_AVAILABLE): Agent
    {
        $ext = $tenant->extensions()->create([
            'extension' => (string) fake()->unique()->numberBetween(1000, 9999),
            'password' => 'secret123',
            'directory_first_name' => fake()->firstName(),
            'directory_last_name' => fake()->lastName(),
        ]);

        return Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $ext->id,
            'name' => fake()->name(),
            'state' => $state,
        ]);
    }

    public function test_real_time_metrics_with_no_entries(): void
    {
        [$tenant, $queue] = $this->createSetup();

        $metrics = $this->metricsService->getRealTimeMetrics($queue);

        $this->assertEquals(0, $metrics['waiting_count']);
        $this->assertEquals(0, $metrics['calls_offered']);
        $this->assertEquals(0, $metrics['calls_answered']);
        $this->assertEquals(0, $metrics['calls_abandoned']);
        $this->assertEquals(0, $metrics['average_wait_time']);
        $this->assertEquals(100, $metrics['service_level']); // 100% when no calls
    }

    public function test_real_time_metrics_with_entries(): void
    {
        [$tenant, $queue] = $this->createSetup();

        // Create answered entry within SLA
        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(5),
            'answer_time' => now()->subMinutes(4),
            'wait_duration' => 15,
        ]);

        // Create answered entry outside SLA
        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(3),
            'answer_time' => now()->subMinutes(2),
            'wait_duration' => 30,
        ]);

        // Create abandoned entry
        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ABANDONED,
            'join_time' => now()->subMinutes(2),
            'abandon_time' => now()->subMinute(),
            'wait_duration' => 60,
        ]);

        // Create waiting entry
        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_WAITING,
            'join_time' => now(),
        ]);

        $metrics = $this->metricsService->getRealTimeMetrics($queue);

        $this->assertEquals(1, $metrics['waiting_count']);
        $this->assertEquals(4, $metrics['calls_offered']);
        $this->assertEquals(2, $metrics['calls_answered']);
        $this->assertEquals(1, $metrics['calls_abandoned']);
        $this->assertEquals(22.5, $metrics['average_wait_time']); // (15 + 30) / 2
        $this->assertEquals(30, $metrics['max_wait_time']);
    }

    public function test_abandon_rate_calculation(): void
    {
        [$tenant, $queue] = $this->createSetup();

        // 1 answered, 1 abandoned = 50% abandon rate
        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(5),
            'answer_time' => now()->subMinutes(4),
            'wait_duration' => 10,
        ]);

        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ABANDONED,
            'join_time' => now()->subMinutes(3),
            'abandon_time' => now()->subMinutes(2),
            'wait_duration' => 60,
        ]);

        $metrics = $this->metricsService->getRealTimeMetrics($queue);

        $this->assertEquals(50.0, $metrics['abandon_rate']);
    }

    public function test_service_level_calculation(): void
    {
        [$tenant, $queue] = $this->createSetup();
        $queue->update(['service_level_threshold' => 20]);

        // 1 within SLA (15s), 1 outside SLA (30s), 1 abandoned
        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(10),
            'answer_time' => now()->subMinutes(9),
            'wait_duration' => 15,
        ]);

        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(5),
            'answer_time' => now()->subMinutes(4),
            'wait_duration' => 30,
        ]);

        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ABANDONED,
            'join_time' => now()->subMinutes(2),
            'abandon_time' => now()->subMinute(),
            'wait_duration' => 60,
        ]);

        $metrics = $this->metricsService->getRealTimeMetrics($queue);

        // 1 out of 3 within SLA = 33.33%
        $this->assertEquals(33.33, $metrics['service_level']);
    }

    public function test_agent_occupancy_calculation(): void
    {
        [$tenant, $queue] = $this->createSetup();

        $agent1 = $this->createAgentForTenant($tenant, Agent::STATE_BUSY);
        $agent2 = $this->createAgentForTenant($tenant, Agent::STATE_AVAILABLE);

        $queue->members()->attach($agent1->id, ['id' => Str::uuid(), 'priority' => 0]);
        $queue->members()->attach($agent2->id, ['id' => Str::uuid(), 'priority' => 1]);

        $metrics = $this->metricsService->getRealTimeMetrics($queue);

        $this->assertEquals(50.0, $metrics['agent_occupancy']); // 1 of 2 busy
    }

    public function test_aggregate_metrics_creates_record(): void
    {
        [$tenant, $queue] = $this->createSetup();

        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->startOfHour()->addMinutes(5),
            'answer_time' => now()->startOfHour()->addMinutes(6),
            'wait_duration' => 15,
        ]);

        $metric = $this->metricsService->aggregateMetrics($queue, 'hourly', now()->startOfHour());

        $this->assertDatabaseHas('queue_metrics', [
            'id' => $metric->id,
            'queue_id' => $queue->id,
            'period' => 'hourly',
            'calls_offered' => 1,
            'calls_answered' => 1,
        ]);
    }

    public function test_agent_states_summary(): void
    {
        [$tenant] = $this->createSetup();

        $this->createAgentForTenant($tenant, Agent::STATE_AVAILABLE);
        $this->createAgentForTenant($tenant, Agent::STATE_AVAILABLE);
        $this->createAgentForTenant($tenant, Agent::STATE_BUSY);
        $this->createAgentForTenant($tenant, Agent::STATE_PAUSED);
        $this->createAgentForTenant($tenant, Agent::STATE_OFFLINE);

        $summary = $this->metricsService->getAgentStatesSummary($tenant->id);

        $this->assertEquals(2, $summary[Agent::STATE_AVAILABLE]);
        $this->assertEquals(1, $summary[Agent::STATE_BUSY]);
        $this->assertEquals(1, $summary[Agent::STATE_PAUSED]);
        $this->assertEquals(1, $summary[Agent::STATE_OFFLINE]);
        $this->assertEquals(0, $summary[Agent::STATE_RINGING]);
    }

    public function test_wallboard_data_structure(): void
    {
        [$tenant, $queue] = $this->createSetup();

        $agent = $this->createAgentForTenant($tenant);
        $queue->members()->attach($agent->id, ['id' => Str::uuid(), 'priority' => 0]);

        $wallboard = $this->metricsService->getWallboardData($tenant->id);

        $this->assertArrayHasKey('queues', $wallboard);
        $this->assertArrayHasKey('agent_states', $wallboard);
        $this->assertArrayHasKey('agents', $wallboard);
        $this->assertCount(1, $wallboard['queues']);
        $this->assertCount(1, $wallboard['agents']);
    }

    public function test_tenant_isolation_in_metrics(): void
    {
        [$tenant1, $queue1] = $this->createSetup();

        $tenant2 = Tenant::create([
            'name' => 'Other Corp',
            'domain' => 'other.example.com',
            'slug' => 'other-corp',
            'max_extensions' => 50,
        ]);

        $queue2 = Queue::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Other Queue',
        ]);

        QueueEntry::create([
            'tenant_id' => $tenant1->id,
            'queue_id' => $queue1->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now()->subMinutes(5),
            'wait_duration' => 10,
        ]);

        $metrics2 = $this->metricsService->getRealTimeMetrics($queue2);
        $this->assertEquals(0, $metrics2['calls_offered']);

        $wallboard2 = $this->metricsService->getWallboardData($tenant2->id);
        $this->assertCount(1, $wallboard2['queues']);
        $this->assertEquals(0, $wallboard2['queues'][0]['calls_offered']);
    }
}
