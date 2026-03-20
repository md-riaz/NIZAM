<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\Did;
use App\Models\Tenant;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DidBridgeRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_did_can_route_to_bridge_destination(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'tenant.example.com', 'is_active' => true]);
        $gateway = $tenant->gateways()->create([
            'name' => 'Carrier A',
            'host' => 'sip.carrier.test',
            'port' => 5060,
            'transport' => 'udp',
            'register' => false,
            'profile' => 'external',
            'is_active' => true,
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => $gateway->id,
            'destination_template' => '+15550123456',
            'bridge_type' => 'gateway',
            'is_active' => true,
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15550002222',
            'destination_type' => 'bridge',
            'destination_id' => $bridge->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan($tenant->domain, '+15550002222');

        $this->assertStringContainsString('sofia/gateway/v_'.$gateway->id.'/+15550123456', $xml);
    }
}
