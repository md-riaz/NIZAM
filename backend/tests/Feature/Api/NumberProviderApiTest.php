<?php

namespace Tests\Feature\Api;

use App\Models\Bridge;
use App\Models\Did;
use App\Models\Gateway;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberProviderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'role' => 'admin']);
    }

    public function test_create_provider_for_number_creates_gateway_and_links_did(): void
    {
        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => null,
        ]);

        $payload = [
            'name' => 'Main Number Provider',
            'host' => 'sip.example.com',
            'username' => 'acct-1001',
            'password' => 'secret',
            'register' => true,
            'is_active' => true,
        ];

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}/provider", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.gateway_id', fn ($gatewayId) => filled($gatewayId))
            ->assertJsonPath('data.gateway.name', 'Main Number Provider')
            ->assertJsonPath('data.gateway.host', 'sip.example.com')
            ->assertJsonPath('data.gateway.username', 'acct-1001')
            ->assertJsonPath('data.gateway.register', true)
            ->assertJsonPath('data.gateway.is_active', true);

        $did->refresh();

        $this->assertNotNull($did->gateway_id);
        $this->assertDatabaseHas('gateways', [
            'id' => $did->gateway_id,
            'organization_id' => $this->organization->id,
            'name' => 'Main Number Provider',
            'host' => 'sip.example.com',
            'username' => 'acct-1001',
            'profile' => 'external',
        ]);
    }

    public function test_update_provider_for_number_updates_existing_gateway(): void
    {
        $gateway = Gateway::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Old Provider',
            'host' => 'old.example.com',
            'register' => true,
        ]);

        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}/provider", [
                'name' => 'Updated Provider',
                'host' => 'new.example.com',
                'username' => 'acct-2002',
                'password' => 'new-secret',
                'register' => false,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.gateway_id', $gateway->id)
            ->assertJsonPath('data.gateway.id', $gateway->id)
            ->assertJsonPath('data.gateway.name', 'Updated Provider')
            ->assertJsonPath('data.gateway.host', 'new.example.com')
            ->assertJsonPath('data.gateway.register', false);

        $this->assertDatabaseHas('gateways', [
            'id' => $gateway->id,
            'organization_id' => $this->organization->id,
            'name' => 'Updated Provider',
            'host' => 'new.example.com',
            'username' => 'acct-2002',
            'register' => false,
        ]);
    }

    public function test_delete_provider_for_number_unlinks_and_deletes_gateway(): void
    {
        $gateway = Gateway::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Dedicated Provider',
        ]);

        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}/provider")
            ->assertOk()
            ->assertJsonPath('data.gateway_id', null)
            ->assertJsonPath('data.gateway', null);

        $did->refresh();

        $this->assertNull($did->gateway_id);
        $this->assertDatabaseMissing('gateways', ['id' => $gateway->id]);
    }

    public function test_delete_provider_for_number_keeps_gateway_when_other_number_still_uses_it(): void
    {
        $gateway = Gateway::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Shared Provider',
        ]);

        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
        ]);

        Did::factory()->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}/provider")
            ->assertOk()
            ->assertJsonPath('data.gateway_id', null)
            ->assertJsonPath('data.gateway', null);

        $did->refresh();

        $this->assertNull($did->gateway_id);
        $this->assertDatabaseHas('gateways', ['id' => $gateway->id]);
    }

    public function test_delete_provider_for_number_keeps_gateway_when_bridge_still_uses_it(): void
    {
        $gateway = Gateway::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bridge Provider',
        ]);

        $did = Did::factory()->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
        ]);

        Bridge::factory()->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/dids/{$did->id}/provider")
            ->assertOk()
            ->assertJsonPath('data.gateway_id', null)
            ->assertJsonPath('data.gateway', null);

        $did->refresh();

        $this->assertNull($did->gateway_id);
        $this->assertDatabaseHas('gateways', ['id' => $gateway->id]);
    }
}
