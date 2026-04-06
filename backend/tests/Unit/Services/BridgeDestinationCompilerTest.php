<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\CallRoutingPolicy;
use App\Models\Tenant;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeDestinationCompilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_can_compile_bridge_destination(): void
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
            'destination_template' => '+15551234567',
            'bridge_type' => 'gateway',
            'is_active' => true,
        ]);
        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'match_destination_type' => 'bridge',
            'match_destination_id' => $bridge->id,
            'no_match_destination_type' => null,
            'no_match_destination_id' => null,
        ]);
        $tenant->dids()->create([
            'number' => '+15550009999',
            'destination_type' => 'call_routing_policy',
            'destination_id' => $policy->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan($tenant->domain, '+15550009999');

        $this->assertStringContainsString('sofia/gateway/v_'.$gateway->id.'/+15551234567', $xml);
    }
}
