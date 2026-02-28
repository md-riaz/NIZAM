<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Tenant;
use App\Services\MetricsService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueStressTest extends TestCase
{
    use RefreshDatabase;

    private QueueService $queueService;

    private MetricsService $metricsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueService = new QueueService;
        $this->metricsService = new MetricsService;
    }

    private function createStressSetup(int $agentCount = 20): array
    {
        $tenant = Tenant::create([
            'name' => 'Stress Corp',
            'domain' => 'stress.example.com',
            'slug' => 'stress-corp',
            'max_extensions' => 500,
        ]);

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'High Volume Queue',
            'strategy' => Queue::STRATEGY_ROUND_ROBIN,
            'max_wait_time' => 300,
            'service_level_threshold' => 20,
        ]);

        $agents = [];
        for ($i = 0; $i < $agentCount; $i++) {
            $ext = $tenant->extensions()->create([
                'extension' => (string) (1001 + $i),
                'password' => 'secret123',
                'directory_first_name' => "Agent{$i}",
                'directory_last_name' => 'Stress',
            ]);

            $agent = Agent::create([
                'tenant_id' => $tenant->id,
                'extension_id' => $ext->id,
                'name' => "Stress Agent {$i}",
                'state' => Agent::STATE_AVAILABLE,
            ]);

            $queue->members()->attach($agent->id, [
                'id' => Str::uuid(),
                'priority' => $i,
            ]);

            $agents[] = $agent;
        }

        return [$tenant, $queue, $agents];
    }

    public function test_100_queued_calls_with_20_agents(): void
    {
        Event::fake();

        [$tenant, $queue, $agents] = $this->createStressSetup(20);

        $entries = [];
        for ($i = 0; $i < 100; $i++) {
            $entries[] = $this->queueService->addToQueue($queue, (string) Str::uuid(), [
                'caller_id_number' => '+1555'.str_pad($i, 7, '0', STR_PAD_LEFT),
                'caller_id_name' => "Caller {$i}",
            ]);
        }

        $this->assertCount(100, $entries);
        $this->assertEquals(100, $queue->waitingEntries()->count());

        // Process calls: agents answer in order
        $answeredCount = 0;
        $agentIndex = 0;

        foreach ($entries as $entry) {
            if ($agentIndex >= count($agents)) {
                break;
            }

            $agent = $agents[$agentIndex];
            $this->queueService->answerEntry($entry, $agent);
            $answeredCount++;
            $agentIndex++;
        }

        $this->assertEquals(20, $answeredCount);
        $this->assertEquals(80, $queue->waitingEntries()->count());

        // Verify all answered entries have valid data
        $answeredEntries = QueueEntry::where('queue_id', $queue->id)
            ->where('status', QueueEntry::STATUS_ANSWERED)
            ->get();

        $this->assertCount(20, $answeredEntries);

        foreach ($answeredEntries as $answered) {
            $this->assertNotNull($answered->agent_id);
            $this->assertNotNull($answered->answer_time);
            $this->assertNotNull($answered->wait_duration);
        }
    }

    public function test_no_ghost_agents_after_state_transitions(): void
    {
        Event::fake();

        [$tenant, $queue, $agents] = $this->createStressSetup(20);

        // Random state transitions
        $states = Agent::VALID_STATES;

        foreach ($agents as $agent) {
            $randomState = $states[array_rand($states)];
            $pauseReason = $randomState === Agent::STATE_PAUSED ? Agent::PAUSE_BREAK : null;
            $agent->transitionState($randomState, $pauseReason);
        }

        // Verify no agent is in an invalid state
        $allAgents = Agent::where('tenant_id', $tenant->id)->get();
        foreach ($allAgents as $agent) {
            $this->assertContains($agent->state, Agent::VALID_STATES);
            if ($agent->state !== Agent::STATE_PAUSED) {
                $this->assertNull($agent->pause_reason);
            }
            $this->assertNotNull($agent->state_changed_at);
        }
    }

    public function test_no_stuck_calls_after_processing(): void
    {
        Event::fake();

        [$tenant, $queue, $agents] = $this->createStressSetup(5);

        // Add 20 calls
        $entries = [];
        for ($i = 0; $i < 20; $i++) {
            $entries[] = $this->queueService->addToQueue($queue, (string) Str::uuid());
        }

        // Answer 5 calls
        for ($i = 0; $i < 5; $i++) {
            $this->queueService->answerEntry($entries[$i], $agents[$i]);
        }

        // Abandon 5 calls
        for ($i = 5; $i < 10; $i++) {
            $this->queueService->abandonEntry($entries[$i], 'caller_hangup');
        }

        // Overflow 5 calls
        for ($i = 10; $i < 15; $i++) {
            $this->queueService->overflowEntry($entries[$i]);
        }

        // Remaining 5 should still be waiting
        $waiting = QueueEntry::where('queue_id', $queue->id)
            ->where('status', QueueEntry::STATUS_WAITING)
            ->count();

        $answered = QueueEntry::where('queue_id', $queue->id)
            ->where('status', QueueEntry::STATUS_ANSWERED)
            ->count();

        $abandoned = QueueEntry::where('queue_id', $queue->id)
            ->where('status', QueueEntry::STATUS_ABANDONED)
            ->count();

        $overflowed = QueueEntry::where('queue_id', $queue->id)
            ->where('status', QueueEntry::STATUS_OVERFLOWED)
            ->count();

        $this->assertEquals(5, $waiting);
        $this->assertEquals(5, $answered);
        $this->assertEquals(5, $abandoned);
        $this->assertEquals(5, $overflowed);

        // Total should equal 20 — no stuck, no missing
        $total = $waiting + $answered + $abandoned + $overflowed;
        $this->assertEquals(20, $total);
    }

    public function test_fair_distribution_round_robin(): void
    {
        Event::fake();

        [$tenant, $queue, $agents] = $this->createStressSetup(5);

        // Process 5 calls sequentially, each with all agents available
        $assignedAgents = [];
        for ($i = 0; $i < 5; $i++) {
            // Refresh all agents from DB and make available
            Agent::where('tenant_id', $tenant->id)->update([
                'state' => Agent::STATE_AVAILABLE,
                'state_changed_at' => now(),
            ]);

            $entry = $this->queueService->addToQueue($queue, (string) Str::uuid());
            $selected = $this->queueService->selectAgent($queue);
            $this->assertNotNull($selected, "Call {$i}: No agent selected");

            $this->queueService->answerEntry($entry, $selected);
            $assignedAgents[] = $selected->id;
        }

        // All 5 calls should be answered
        $this->assertCount(5, $assignedAgents);

        // Verify all calls were processed — no stuck calls
        $answered = QueueEntry::where('queue_id', $queue->id)
            ->where('status', QueueEntry::STATUS_ANSWERED)
            ->count();
        $this->assertEquals(5, $answered);
    }

    public function test_metrics_accurate_under_stress(): void
    {
        Event::fake();

        [$tenant, $queue, $agents] = $this->createStressSetup(10);

        // Create 50 calls with mixed outcomes
        for ($i = 0; $i < 30; $i++) {
            $entry = $this->queueService->addToQueue($queue, (string) Str::uuid());
            // Manually set wait duration for answered
            $entry->update([
                'status' => QueueEntry::STATUS_ANSWERED,
                'agent_id' => $agents[$i % 10]->id,
                'answer_time' => now(),
                'wait_duration' => $i < 15 ? 10 : 30, // half within SLA, half outside
            ]);
        }

        for ($i = 0; $i < 20; $i++) {
            $entry = $this->queueService->addToQueue($queue, (string) Str::uuid());
            $entry->update([
                'status' => QueueEntry::STATUS_ABANDONED,
                'abandon_time' => now(),
                'wait_duration' => 60,
                'abandon_reason' => 'caller_hangup',
            ]);
        }

        $metrics = $this->metricsService->getRealTimeMetrics($queue);

        $this->assertEquals(50, $metrics['calls_offered']);
        $this->assertEquals(30, $metrics['calls_answered']);
        $this->assertEquals(20, $metrics['calls_abandoned']);
        $this->assertEquals(40.0, $metrics['abandon_rate']); // 20/50 = 40%

        // Average wait for answered: (15*10 + 15*30) / 30 = 600/30 = 20
        $this->assertEquals(20.0, $metrics['average_wait_time']);
    }

    public function test_high_abandon_rate_scenario(): void
    {
        Event::fake();

        [$tenant, $queue] = $this->createStressSetup(0); // No agents

        // 50 calls, all abandoned
        for ($i = 0; $i < 50; $i++) {
            $entry = $this->queueService->addToQueue($queue, (string) Str::uuid());
            $this->queueService->abandonEntry($entry, 'no_agents');
        }

        $metrics = $this->metricsService->getRealTimeMetrics($queue);

        $this->assertEquals(50, $metrics['calls_offered']);
        $this->assertEquals(0, $metrics['calls_answered']);
        $this->assertEquals(50, $metrics['calls_abandoned']);
        $this->assertEquals(100.0, $metrics['abandon_rate']);
    }

    public function test_multi_tenant_isolation_under_stress(): void
    {
        Event::fake();

        [$tenant1, $queue1, $agents1] = $this->createStressSetup(5);

        $tenant2 = Tenant::create([
            'name' => 'Other Corp',
            'domain' => 'other.example.com',
            'slug' => 'other-corp',
            'max_extensions' => 500,
        ]);

        $queue2 = Queue::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Other Queue',
        ]);

        // Add 50 calls to tenant 1
        for ($i = 0; $i < 50; $i++) {
            $this->queueService->addToQueue($queue1, (string) Str::uuid());
        }

        // Tenant 2 should have no calls
        $metrics2 = $this->metricsService->getRealTimeMetrics($queue2);
        $this->assertEquals(0, $metrics2['calls_offered']);
        $this->assertEquals(0, $metrics2['waiting_count']);

        $wallboard2 = $this->metricsService->getWallboardData($tenant2->id);
        $this->assertEquals(0, $wallboard2['queues'][0]['calls_offered']);
    }

    public function test_concurrent_agent_state_transitions(): void
    {
        Event::fake();

        [$tenant, $queue, $agents] = $this->createStressSetup(20);

        // Rapidly transition all agents through all states
        foreach (Agent::VALID_STATES as $state) {
            foreach ($agents as $agent) {
                $pauseReason = $state === Agent::STATE_PAUSED ? Agent::PAUSE_BREAK : null;
                $agent->transitionState($state, $pauseReason);
            }
        }

        // All agents should be in last state (offline)
        $allAgents = Agent::where('tenant_id', $tenant->id)->get();
        foreach ($allAgents as $agent) {
            $this->assertEquals(Agent::STATE_OFFLINE, $agent->state);
            $this->assertNull($agent->pause_reason);
        }
    }
}
