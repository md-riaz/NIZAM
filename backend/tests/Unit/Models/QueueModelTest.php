<?php

namespace Tests\Unit\Models;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueModelTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Test Corp',
            'domain' => 'test.example.com',
            'slug' => 'test-corp',
            'max_extensions' => 50,
        ]);
    }

    public function test_can_be_created_with_valid_attributes(): void
    {
        $tenant = $this->createTenant();

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support Queue',
            'strategy' => Queue::STRATEGY_ROUND_ROBIN,
            'max_wait_time' => 120,
            'overflow_action' => Queue::OVERFLOW_VOICEMAIL,
            'service_level_threshold' => 20,
        ]);

        $this->assertDatabaseHas('queues', [
            'id' => $queue->id,
            'name' => 'Support Queue',
            'strategy' => 'round_robin',
        ]);
    }

    public function test_belongs_to_tenant(): void
    {
        $tenant = $this->createTenant();

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support Queue',
        ]);

        $this->assertEquals($tenant->id, $queue->tenant->id);
    }

    public function test_has_valid_strategy_constants(): void
    {
        $this->assertContains('ring_all', Queue::VALID_STRATEGIES);
        $this->assertContains('round_robin', Queue::VALID_STRATEGIES);
        $this->assertContains('least_recent', Queue::VALID_STRATEGIES);
    }

    public function test_has_valid_overflow_constants(): void
    {
        $this->assertContains('voicemail', Queue::VALID_OVERFLOW_ACTIONS);
        $this->assertContains('hangup', Queue::VALID_OVERFLOW_ACTIONS);
        $this->assertContains('extension', Queue::VALID_OVERFLOW_ACTIONS);
    }

    public function test_has_entries_relationship(): void
    {
        $tenant = $this->createTenant();

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support Queue',
        ]);

        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'join_time' => now(),
        ]);

        $this->assertCount(1, $queue->entries);
    }

    public function test_waiting_entries_scope(): void
    {
        $tenant = $this->createTenant();

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support Queue',
        ]);

        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_WAITING,
            'join_time' => now(),
        ]);

        QueueEntry::create([
            'tenant_id' => $tenant->id,
            'queue_id' => $queue->id,
            'call_uuid' => (string) Str::uuid(),
            'status' => QueueEntry::STATUS_ANSWERED,
            'join_time' => now(),
        ]);

        $this->assertCount(1, $queue->waitingEntries);
    }

    public function test_has_members_relationship(): void
    {
        $tenant = $this->createTenant();

        $queue = Queue::create([
            'tenant_id' => $tenant->id,
            'name' => 'Support Queue',
        ]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $agent = Agent::create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent 1',
        ]);

        $queue->members()->attach($agent->id, [
            'id' => Str::uuid(),
            'priority' => 0,
        ]);

        $this->assertCount(1, $queue->fresh()->members);
    }
}
