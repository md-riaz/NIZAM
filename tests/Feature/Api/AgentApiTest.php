<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Extension $extension;

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

        $this->extension = $this->tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);
    }

    public function test_can_list_agents(): void
    {
        Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/agents");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_create_agent(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/agents", [
                'extension_id' => $this->extension->id,
                'name' => 'Agent Smith',
                'role' => 'agent',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Agent Smith', 'role' => 'agent']);

        $this->assertDatabaseHas('agents', [
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);
    }

    public function test_cannot_create_duplicate_agent_for_extension(): void
    {
        Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/agents", [
                'extension_id' => $this->extension->id,
                'name' => 'Agent Jones',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('extension_id');
    }

    public function test_can_show_agent(): void
    {
        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/agents/{$agent->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Agent Smith']);
    }

    public function test_can_update_agent(): void
    {
        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/agents/{$agent->id}", [
                'name' => 'Agent Jones',
                'role' => 'supervisor',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Agent Jones', 'role' => 'supervisor']);
    }

    public function test_can_delete_agent(): void
    {
        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/agents/{$agent->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
    }

    public function test_can_change_agent_state(): void
    {
        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_OFFLINE,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/agents/{$agent->id}/state", [
                'state' => 'available',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['state' => 'available']);

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'state' => 'available',
        ]);
    }

    public function test_can_pause_agent_with_reason(): void
    {
        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_AVAILABLE,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/agents/{$agent->id}/state", [
                'state' => 'paused',
                'pause_reason' => 'lunch',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['state' => 'paused', 'pause_reason' => 'lunch']);
    }

    public function test_pause_requires_reason(): void
    {
        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_AVAILABLE,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/agents/{$agent->id}/state", [
                'state' => 'paused',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('pause_reason');
    }

    public function test_invalid_state_rejected(): void
    {
        $agent = Agent::create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/agents/{$agent->id}/state", [
                'state' => 'invalid_state',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('state');
    }

    public function test_tenant_isolation_for_agents(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Corp',
            'domain' => 'other.example.com',
            'slug' => 'other-corp',
            'max_extensions' => 50,
        ]);

        $otherExt = $otherTenant->extensions()->create([
            'extension' => '2001',
            'password' => 'secret123',
            'directory_first_name' => 'Jane',
            'directory_last_name' => 'Doe',
        ]);

        $otherAgent = Agent::create([
            'tenant_id' => $otherTenant->id,
            'extension_id' => $otherExt->id,
            'name' => 'Other Agent',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/agents/{$otherAgent->id}");

        $response->assertStatus(404);
    }
}
