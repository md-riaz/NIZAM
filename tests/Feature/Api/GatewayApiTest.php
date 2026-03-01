<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewayApiTest extends TestCase
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

    public function test_can_list_gateways_for_a_tenant(): void
    {
        Gateway::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/gateways");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_gateway(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/gateways", [
                'name' => 'Primary SIP Trunk',
                'host' => '10.0.0.1',
                'port' => 5060,
                'transport' => 'udp',
                'inbound_codecs' => ['PCMU', 'PCMA', 'G722'],
                'outbound_codecs' => ['PCMU', 'PCMA'],
                'allow_transcoding' => true,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('gateways', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Primary SIP Trunk',
            'host' => '10.0.0.1',
        ]);
    }

    public function test_can_show_a_gateway(): void
    {
        $gateway = Gateway::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/gateways/{$gateway->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $gateway->name]);
    }

    public function test_can_update_a_gateway(): void
    {
        $gateway = Gateway::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tenants/{$this->tenant->id}/gateways/{$gateway->id}", [
                'name' => 'Updated Trunk',
                'host' => '10.0.0.2',
                'inbound_codecs' => ['PCMU'],
                'outbound_codecs' => ['PCMU'],
                'allow_transcoding' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('gateways', [
            'id' => $gateway->id,
            'name' => 'Updated Trunk',
        ]);
    }

    public function test_can_delete_a_gateway(): void
    {
        $gateway = Gateway::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/gateways/{$gateway->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('gateways', ['id' => $gateway->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/gateways", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'host']);
    }

    public function test_returns_404_for_wrong_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/gateways/{$gateway->id}");

        $response->assertStatus(404);
    }

    public function test_gateway_resource_does_not_expose_password(): void
    {
        $gateway = Gateway::factory()->create(['tenant_id' => $this->tenant->id, 'password' => 'secret123']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/gateways/{$gateway->id}");

        $response->assertStatus(200);
        $response->assertJsonMissing(['password' => 'secret123']);
    }

    public function test_unauthenticated_requests_return_401(): void
    {
        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/gateways");

        $response->assertStatus(401);
    }
}
