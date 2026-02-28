<?php

namespace Tests\Unit\Services;

use App\Events\ContactCenterEvent;
use App\Models\Agent;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Tenant;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueServiceTest extends TestCase
{
    use RefreshDatabase;

    private QueueService $queueService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueService = new QueueService;
    }

    private function createSetup(int $agentCount = 3): array
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
        ]);

        $agents = [];
        for ($i = 0; $i < $agentCount; $i++) {
            $ext = $tenant->extensions()->create([
                'extension' => (string) (1001 + $i),
                'password' => 'secret123',
                'directory_first_name' => "Agent{$i}",
                'directory_last_name' => 'Test',
            ]);

            $agent = Agent::create([
                'tenant_id' => $tenant->id,
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

        return [$tenant, $queue, $agents];
    }

    public function test_add_to_queue_creates_entry(): void
    {
        Event::fake();

        [$tenant, $queue] = $this->createSetup(1);
        $callUuid = (string) Str::uuid();

        $entry = $this->queueService->addToQueue($queue, $callUuid, [
            'caller_id_number' => '+15551234567',
            'caller_id_name' => 'John',
        ]);

        $this->assertDatabaseHas('queue_entries', [
            'id' => $entry->id,
            'queue_id' => $queue->id,
            'call_uuid' => $callUuid,
            'status' => QueueEntry::STATUS_WAITING,
        ]);

        Event::assertDispatched(ContactCenterEvent::class, function ($event) {
            return $event->eventType === 'queue.call_joined';
        });
    }

    public function test_select_agent_round_robin(): void
    {
        [$tenant, $queue, $agents] = $this->createSetup(3);

        // First selection should return first agent
        $selected = $this->queueService->selectAgent($queue);
        $this->assertEquals($agents[0]->id, $selected->id);
    }

    public function test_select_agent_round_robin_rotates(): void
    {
        Event::fake();

        [$tenant, $queue, $agents] = $this->createSetup(3);

        // Simulate first call answered by agent 0
        $entry = $this->queueService->addToQueue($queue, (string) Str::uuid());
        $this->queueService->answerEntry($entry, $agents[0]);

        // Next selection should skip agent 0 (now busy) and go to next available
        $agents[0]->transitionState(Agent::STATE_AVAILABLE);
        $selected = $this->queueService->selectAgent($queue);
        $this->assertEquals($agents[1]->id, $selected->id);
    }

    public function test_select_agent_least_recent(): void
    {
        Event::fake();

        [$tenant, $queue, $agents] = $this->createSetup(3);
        $queue->update(['strategy' => Queue::STRATEGY_LEAST_RECENT]);

        // Agent 2 never answered — should be selected
        $entry1 = QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'agent_id' => $agents[0]->id,
            'join_time' => now()->subMinutes(10),
            'answer_time' => now()->subMinutes(9),
            'wait_duration' => 60,
        ]);

        $entry2 = QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'agent_id' => $agents[1]->id,
            'join_time' => now()->subMinutes(5),
            'answer_time' => now()->subMinutes(4),
            'wait_duration' => 60,
        ]);

        $selected = $this->queueService->selectAgent($queue);
        $this->assertEquals($agents[2]->id, $selected->id);
    }

    public function test_select_agent_ring_all_returns_first_available(): void
    {
        [$tenant, $queue, $agents] = $this->createSetup(3);
        $queue->update(['strategy' => Queue::STRATEGY_RING_ALL]);

        $selected = $this->queueService->selectAgent($queue);
        $this->assertEquals($agents[0]->id, $selected->id);
    }

    public function test_get_agents_for_ring_all(): void
    {
        [$tenant, $queue, $agents] = $this->createSetup(3);
        $queue->update(['strategy' => Queue::STRATEGY_RING_ALL]);

        $available = $this->queueService->getAgentsForRingAll($queue);
        $this->assertCount(3, $available);
    }

    public function test_select_agent_returns_null_when_none_available(): void
    {
        [$tenant, $queue, $agents] = $this->createSetup(2);

        foreach ($agents as $agent) {
            $agent->transitionState(Agent::STATE_BUSY);
        }

        $selected = $this->queueService->selectAgent($queue);
        $this->assertNull($selected);
    }

    public function test_answer_entry(): void
    {
        Event::fake();

        [$tenant, $queue, $agents] = $this->createSetup(1);
        $entry = $this->queueService->addToQueue($queue, (string) Str::uuid());

        $this->queueService->answerEntry($entry, $agents[0]);
        $entry->refresh();

        $this->assertEquals(QueueEntry::STATUS_ANSWERED, $entry->status);
        $this->assertEquals($agents[0]->id, $entry->agent_id);
        $this->assertNotNull($entry->answer_time);
        $this->assertNotNull($entry->wait_duration);

        // Agent should now be busy
        $agents[0]->refresh();
        $this->assertEquals(Agent::STATE_BUSY, $agents[0]->state);

        Event::assertDispatched(ContactCenterEvent::class, function ($event) {
            return $event->eventType === 'queue.call_answered';
        });
    }

    public function test_abandon_entry(): void
    {
        Event::fake();

        [$tenant, $queue] = $this->createSetup(0);
        $entry = $this->queueService->addToQueue($queue, (string) Str::uuid());

        $this->queueService->abandonEntry($entry, 'caller_hangup');
        $entry->refresh();

        $this->assertEquals(QueueEntry::STATUS_ABANDONED, $entry->status);
        $this->assertNotNull($entry->abandon_time);
        $this->assertEquals('caller_hangup', $entry->abandon_reason);

        Event::assertDispatched(ContactCenterEvent::class, function ($event) {
            return $event->eventType === 'queue.call_abandoned';
        });
    }

    public function test_overflow_entry(): void
    {
        Event::fake();

        [$tenant, $queue] = $this->createSetup(0);
        $entry = $this->queueService->addToQueue($queue, (string) Str::uuid());

        $this->queueService->overflowEntry($entry);
        $entry->refresh();

        $this->assertEquals(QueueEntry::STATUS_OVERFLOWED, $entry->status);
        $this->assertEquals('max_wait_exceeded', $entry->abandon_reason);

        Event::assertDispatched(ContactCenterEvent::class, function ($event) {
            return $event->eventType === 'queue.call_overflowed';
        });
    }

    public function test_overflow_candidates(): void
    {
        [$tenant, $queue] = $this->createSetup(0);
        $queue->update(['max_wait_time' => 60]);

        // Entry within time limit
        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_WAITING,
            'join_time' => now()->subSeconds(30),
        ]);

        // Entry exceeding time limit
        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_WAITING,
            'join_time' => now()->subSeconds(120),
        ]);

        $candidates = $this->queueService->getOverflowCandidates($queue);
        $this->assertCount(1, $candidates);
    }

    public function test_skips_paused_agents_in_selection(): void
    {
        [$tenant, $queue, $agents] = $this->createSetup(3);

        $agents[0]->transitionState(Agent::STATE_PAUSED, Agent::PAUSE_LUNCH);

        $selected = $this->queueService->selectAgent($queue);
        $this->assertEquals($agents[1]->id, $selected->id);
    }

    public function test_skips_inactive_agents_in_selection(): void
    {
        [$tenant, $queue, $agents] = $this->createSetup(3);

        $agents[0]->update(['is_active' => false]);

        $selected = $this->queueService->selectAgent($queue);
        $this->assertEquals($agents[1]->id, $selected->id);
    }
}
