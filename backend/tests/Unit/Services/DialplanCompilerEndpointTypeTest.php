<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\Did;
use App\Models\Tenant;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerEndpointTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_did_bridge_uses_webrtc_codec_defaults_for_wss_calls(): void
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
            'codec_policy' => 'default',
            'is_active' => true,
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15550002222',
            'destination_type' => 'bridge',
            'destination_id' => $bridge->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan(
            $tenant->domain,
            '+15550002222',
            null,
            ['variable_sip_via_protocol' => 'wss'],
        );

        $this->assertStringContainsString('codec_string=OPUS,G722,PCMU,PCMA', $xml);
        $this->assertStringContainsString('sofia/gateway/v_'.$gateway->id.'/+15550123456', $xml);
    }

    public function test_did_bridge_uses_sip_codec_defaults_for_non_webrtc_calls(): void
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
            'codec_policy' => 'default',
            'is_active' => true,
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15550002222',
            'destination_type' => 'bridge',
            'destination_id' => $bridge->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan(
            $tenant->domain,
            '+15550002222',
            null,
            ['variable_sip_via_protocol' => 'udp'],
        );

        $this->assertStringContainsString('codec_string=G722,PCMU,PCMA', $xml);
        $this->assertStringNotContainsString('codec_string=OPUS,G722,PCMU,PCMA', $xml);
    }
}
