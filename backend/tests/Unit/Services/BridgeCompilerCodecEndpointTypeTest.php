<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Services\Routing\BridgeCompiler;
use App\Services\Routing\CodecResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeCompilerCodecEndpointTypeTest extends TestCase
{
    use RefreshDatabase;

    private BridgeCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new BridgeCompiler(new CodecResolutionService);
    }

    public function test_compile_action_uses_sip_default_when_endpoint_type_omitted(): void
    {
        $tenant = Tenant::factory()->create();
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => null,
            'bridge_type' => 'raw',
            'codec_policy' => 'default',
            'destination_template' => 'sofia/external/test',
        ]);

        $xml = $this->compiler->compileAction($tenant, $bridge);

        // SIP default is G722,PCMU,PCMA
        $this->assertStringContainsString('codec_string', $xml);
        $this->assertStringContainsString('G722,PCMU,PCMA', $xml);
        $this->assertStringNotContainsString('OPUS', $xml);
    }

    public function test_compile_action_uses_webrtc_default_when_endpoint_type_is_webrtc(): void
    {
        $tenant = Tenant::factory()->create();
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => null,
            'bridge_type' => 'raw',
            'codec_policy' => 'default',
            'destination_template' => 'sofia/external/test',
        ]);

        $xml = $this->compiler->compileAction($tenant, $bridge, false, 'webrtc');

        // WebRTC default should include OPUS
        $this->assertStringContainsString('codec_string', $xml);
        $this->assertStringContainsString('OPUS', $xml);
    }

    public function test_compile_action_honors_webrtc_transcode_policy(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'preferred_codecs' => ['PCMU'], // Gateway wants PCMU
            'allow_transcoding' => true,
        ]);
        
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => $gateway->id,
            'bridge_type' => 'gateway',
            'codec_policy' => 'default',
            'transcode_policy' => 'web_only', // Only transcode for WebRTC!
            'destination_template' => '+15551234567',
        ]);

        // SIP endpoint - should NOT have media mix (since web_only)
        $sipXml = $this->compiler->compileAction($tenant, $bridge, false, 'sip');
        $this->assertStringNotContainsString('media_mix_inbound_outbound_codecs=true', $sipXml);

        // WebRTC endpoint - SHOULD have media mix (since web_only)
        $webrtcXml = $this->compiler->compileAction($tenant, $bridge, false, 'webrtc');
        $this->assertStringContainsString('media_mix_inbound_outbound_codecs=true', $webrtcXml);
    }
}
