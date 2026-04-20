<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewaySchemaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_can_store_extended_carrier_fields(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $payload = [
            'name' => 'Carrier A',
            'host' => 'sip.carrier.test',
            'proxy' => 'proxy.carrier.test:5060',
            'register_proxy' => 'reg.carrier.test:5060',
            'port' => 5060,
            'username' => 'user1',
            'password' => 'secret',
            'realm' => 'carrier.test',
            'from_domain' => 'from.carrier.test',
            'extension' => '8801555000000',
            'transport' => 'udp',
            'register' => true,
            'expire_seconds' => 600,
            'retry_seconds' => 15,
            'caller_id_in_from' => true,
            'profile' => 'external',
            'is_active' => true,
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/gateways", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.proxy', 'proxy.carrier.test:5060')
            ->assertJsonPath('data.register_proxy', 'reg.carrier.test:5060')
            ->assertJsonPath('data.from_domain', 'from.carrier.test')
            ->assertJsonPath('data.extension', '8801555000000')
            ->assertJsonPath('data.expire_seconds', 600)
            ->assertJsonPath('data.retry_seconds', 15)
            ->assertJsonPath('data.profile', 'external');
    }
}
