<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Services\Routing\BridgeCompiler;
use App\Services\Routing\CodecResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeCompilerCodecTest extends TestCase
{
    use RefreshDatabase;

    private BridgeCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new BridgeCompiler(new CodecResolutionService);
    }

    public function test_compile_action_includes_codec_string_for_default_policy(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'preferred_codecs' => ['PCMU', 'PCMA'],
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => $gateway->id,
            'bridge_type' => 'gateway',
            'codec_policy' => 'default',
            'destination_template' => '+15551234567',
        ]);

        $xml = $this->compiler->compileAction($tenant, $bridge);

        $this->assertStringContainsString('codec_string', $xml);
        $this->assertStringContainsString('PCMU,PCMA', $xml);
        $this->assertStringContainsString('sofia/gateway/v_'.$gateway->id.'/', $xml);
    }

    public function test_compile_action_includes_absolute_codec_string_for_exact_policy(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'outbound_codecs' => ['PCMU'],
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => $gateway->id,
            'bridge_type' => 'gateway',
            'codec_policy' => 'exact',
            'codec_list' => ['G729'],
            'destination_template' => '+15551234567',
        ]);

        $xml = $this->compiler->compileAction($tenant, $bridge);

        $this->assertStringContainsString('absolute_codec_string', $xml);
        $this->assertStringContainsString('G729', $xml);
    }

    public function test_compile_action_includes_inherit_codec_for_inherit_policy(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create(['tenant_id' => $tenant->id]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => $gateway->id,
            'bridge_type' => 'gateway',
            'codec_policy' => 'inherit',
            'destination_template' => '+15551234567',
        ]);

        $xml = $this->compiler->compileAction($tenant, $bridge);

        $this->assertStringContainsString('inherit_codec=true', $xml);
        $this->assertStringNotContainsString('absolute_codec_string', $xml);
        $this->assertStringNotContainsString('codec_string=', $xml);
    }

    public function test_compile_action_includes_media_mix_when_transcoding_allowed(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'preferred_codecs' => ['PCMU'],
            'allow_transcoding' => true,
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => $gateway->id,
            'bridge_type' => 'gateway',
            'codec_policy' => 'default',
            'transcode_policy' => 'allow',
            'destination_template' => '+15551234567',
        ]);

        $xml = $this->compiler->compileAction($tenant, $bridge);

        $this->assertStringContainsString('media_mix_inbound_outbound_codecs=true', $xml);
    }

    public function test_compile_action_omits_media_mix_when_transcoding_disabled(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'preferred_codecs' => ['PCMU'],
            'allow_transcoding' => false,
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => $gateway->id,
            'bridge_type' => 'gateway',
            'codec_policy' => 'default',
            'transcode_policy' => 'none',
            'destination_template' => '+15551234567',
        ]);

        $xml = $this->compiler->compileAction($tenant, $bridge);

        $this->assertStringNotContainsString('media_mix_inbound_outbound_codecs', $xml);
    }

    public function test_compile_action_raw_bridge_still_includes_codec_vars(): void
    {
        $tenant = Tenant::factory()->create();
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => null,
            'bridge_type' => 'raw',
            'codec_policy' => 'default',
            'destination_template' => 'sofia/external/support@example.com',
        ]);

        // Raw bridge, no gateway — should still compile without errors
        $xml = $this->compiler->compileAction($tenant, $bridge);

        $this->assertStringContainsString('application="bridge"', $xml);
        $this->assertStringContainsString('sofia/external/support@example.com', $xml);
    }

    public function test_compile_anti_action_uses_anti_action_tag(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create(['tenant_id' => $tenant->id]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => $gateway->id,
            'bridge_type' => 'gateway',
            'destination_template' => '+15551234567',
        ]);

        $xml = $this->compiler->compileAction($tenant, $bridge, anti: true);

        $this->assertStringContainsString('<anti-action', $xml);
    }
}
