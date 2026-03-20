<?php

namespace Tests\Feature\Api;

use App\Models\Bridge;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
    }

    public function test_can_list_bridges_for_a_tenant(): void
    {
        Bridge::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/bridges");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_gateway_bridge(): void
    {
        $gateway = Gateway::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/bridges", [
                'name' => 'PSTN Out',
                'bridge_type' => 'gateway',
                'gateway_id' => $gateway->id,
                'destination_template' => '+15551234567',
                'is_active' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.bridge_type', 'gateway');
        $this->assertDatabaseHas('bridges', [
            'tenant_id' => $this->tenant->id,
            'name' => 'PSTN Out',
            'gateway_id' => $gateway->id,
        ]);
    }

    public function test_can_update_a_bridge(): void
    {
        $bridge = Bridge::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tenants/{$this->tenant->id}/bridges/{$bridge->id}", [
                'name' => 'Updated Bridge',
                'destination_template' => '+15550001111',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('bridges', [
            'id' => $bridge->id,
            'name' => 'Updated Bridge',
            'destination_template' => '+15550001111',
        ]);
    }

    public function test_can_delete_a_bridge(): void
    {
        $bridge = Bridge::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/bridges/{$bridge->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('bridges', ['id' => $bridge->id]);
    }

    public function test_returns_404_for_wrong_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $bridge = Bridge::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/bridges/{$bridge->id}");

        $response->assertStatus(404);
    }

    public function test_gateway_bridge_requires_gateway_id(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/bridges", [
                'name' => 'Broken Gateway Bridge',
                'bridge_type' => 'gateway',
                'destination_template' => '+15551234567',
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gateway_id']);
    }

    public function test_raw_bridge_must_not_include_gateway_id(): void
    {
        $gateway = Gateway::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/bridges", [
                'name' => 'Broken Raw Bridge',
                'bridge_type' => 'raw',
                'gateway_id' => $gateway->id,
                'destination_template' => 'sofia/external/support@example.com',
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gateway_id']);
    }
}
