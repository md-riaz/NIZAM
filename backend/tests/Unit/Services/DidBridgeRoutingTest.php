<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\Did;
use App\Models\Organization;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DidBridgeRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_did_can_route_to_bridge_destination(): void
    {
        $organization = Organization::factory()->create(['domain' => 'organization.example.com', 'is_active' => true]);
        $gateway = $organization->gateways()->create([
            'name' => 'Carrier A',
            'host' => 'sip.carrier.test',
            'port' => 5060,
            'transport' => 'udp',
            'register' => false,
            'profile' => 'external',
            'is_active' => true,
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'destination_template' => '+15550123456',
            'bridge_type' => 'gateway',
            'is_active' => true,
        ]);

        Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15550002222',
            'destination_type' => 'bridge',
            'destination_id' => $bridge->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan($organization->domain, '+15550002222');

        $this->assertStringContainsString('sofia/gateway/v_'.$gateway->id.'/+15550123456', $xml);
    }
}
