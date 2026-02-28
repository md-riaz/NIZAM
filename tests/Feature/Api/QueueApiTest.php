<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Corp',
            'domain' => 'test.example.com',
            'slug' => 'test-corp',
            'max_extensions' => 50,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_list_queues(): void
    {
        Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/queues");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_create_queue(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/queues", [
                'name' => 'Support Queue',
                'strategy' => 'round_robin',
                'max_wait_time' => 120,
                'overflow_action' => 'voicemail',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Support Queue', 'strategy' => 'round_robin']);

        $this->assertDatabaseHas('queues', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);
    }

    public function test_cannot_create_duplicate_queue_name(): void
    {
        Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/queues", [
                'name' => 'Support Queue',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_can_update_queue(): void
    {
        $queue = Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/queues/{$queue->id}", [
                'strategy' => 'ring_all',
                'max_wait_time' => 60,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['strategy' => 'ring_all']);
    }

    public function test_can_delete_queue(): void
    {
        $queue = Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/queues/{$queue->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('queues', ['id' => $queue->id]);
    }

    public function test_can_add_member_to_queue(): void
    {
        $queue = Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $extension = $this->tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent 1',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/queues/{$queue->id}/members", [
                'agent_id' => $agent->id,
                'priority' => 1,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('queue_members', [
            'queue_id' => $queue->id,
            'agent_id' => $agent->id,
        ]);
    }

    public function test_cannot_add_duplicate_member(): void
    {
        $queue = Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $extension = $this->tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent 1',
        ]);

        $queue->members()->attach($agent->id, ['id' => Str::uuid(), 'priority' => 0]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/queues/{$queue->id}/members", [
                'agent_id' => $agent->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_remove_member_from_queue(): void
    {
        $queue = Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $extension = $this->tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent 1',
        ]);

        $queue->members()->attach($agent->id, ['id' => Str::uuid(), 'priority' => 0]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/queues/{$queue->id}/members/{$agent->id}");

        $response->assertStatus(204);
    }

    public function test_can_list_queue_members(): void
    {
        $queue = Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $extension = $this->tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Agent 1',
        ]);

        $queue->members()->attach($agent->id, ['id' => Str::uuid(), 'priority' => 0]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/queues/{$queue->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_invalid_strategy_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/queues", [
                'name' => 'Test Queue',
                'strategy' => 'invalid_strategy',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('strategy');
    }

    public function test_tenant_isolation_for_queues(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Corp',
            'domain' => 'other.example.com',
            'slug' => 'other-corp',
            'max_extensions' => 50,
        ]);

        $otherQueue = Queue::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Queue',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/queues/{$otherQueue->id}");

        $response->assertStatus(404);
    }

    public function test_can_get_realtime_metrics(): void
    {
        $queue = Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/queues/{$queue->id}/metrics/realtime");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'queue_id',
                    'queue_name',
                    'waiting_count',
                    'calls_offered',
                    'calls_answered',
                    'calls_abandoned',
                    'average_wait_time',
                    'service_level',
                    'abandon_rate',
                    'agent_occupancy',
                ],
            ]);
    }

    public function test_can_aggregate_metrics(): void
    {
        $queue = Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/queues/{$queue->id}/metrics/aggregate");

        $response->assertStatus(200);
        $this->assertDatabaseHas('queue_metrics', [
            'queue_id' => $queue->id,
        ]);
    }

    public function test_can_get_wallboard(): void
    {
        Queue::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Support Queue',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/wallboard");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'queues',
                    'agent_states',
                    'agents',
                ],
            ]);
    }

    public function test_can_get_agent_states_summary(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/agent-states");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'available',
                    'busy',
                    'ringing',
                    'paused',
                    'offline',
                ],
            ]);
    }
}
