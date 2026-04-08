<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Services\Media\FreeSwitchCommandService;
use App\Services\Media\GatewayProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GatewayCodecRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/gateway-codec-rendering');
        File::deleteDirectory($this->directory);
        File::ensureDirectoryExists($this->directory);
        config()->set('nizam.gateway_provisioning.external_directory', $this->directory);
    }

    private function noOpFreeSwitch(): FreeSwitchCommandService
    {
        return new class extends FreeSwitchCommandService {
            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                return ['executed' => true, 'command' => $command, 'arguments' => $arguments];
            }
        };
    }

    public function test_render_includes_inbound_codec_prefs_when_set(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'inbound_codecs' => ['PCMU', 'PCMA', 'G722'],
            'outbound_codecs' => ['PCMU', 'PCMA'],
            'preferred_codecs' => ['PCMU', 'PCMA'],
            'is_active' => true,
        ]);

        $service = new GatewayProvisioningService($this->noOpFreeSwitch());
        $xml = $service->render($gateway);

        $this->assertStringContainsString('inbound-codec-prefs', $xml);
        $this->assertStringContainsString('PCMU,PCMA,G722', $xml);
    }

    public function test_render_includes_outbound_codec_prefs_from_preferred_codecs(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'preferred_codecs' => ['G722', 'PCMU'],
            'outbound_codecs' => ['PCMU'],
            'is_active' => true,
        ]);

        $service = new GatewayProvisioningService($this->noOpFreeSwitch());
        $xml = $service->render($gateway);

        $this->assertStringContainsString('outbound-codec-prefs', $xml);
        $this->assertStringContainsString('G722,PCMU', $xml);
    }

    public function test_render_falls_back_to_outbound_codecs_when_preferred_is_empty(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'preferred_codecs' => [],
            'outbound_codecs' => ['PCMU', 'PCMA'],
            'is_active' => true,
        ]);

        $service = new GatewayProvisioningService($this->noOpFreeSwitch());
        $xml = $service->render($gateway);

        $this->assertStringContainsString('outbound-codec-prefs', $xml);
        $this->assertStringContainsString('PCMU,PCMA', $xml);
    }

    public function test_render_omits_codec_prefs_when_codecs_are_empty(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'inbound_codecs' => [],
            'outbound_codecs' => [],
            'preferred_codecs' => [],
            'is_active' => true,
        ]);

        $service = new GatewayProvisioningService($this->noOpFreeSwitch());
        $xml = $service->render($gateway);

        $this->assertStringNotContainsString('inbound-codec-prefs', $xml);
        $this->assertStringNotContainsString('outbound-codec-prefs', $xml);
    }

    public function test_render_includes_rtp_secure_media_for_srtp_required(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'srtp_mode' => 'required',
            'is_active' => true,
        ]);

        $service = new GatewayProvisioningService($this->noOpFreeSwitch());
        $xml = $service->render($gateway);

        $this->assertStringContainsString('rtp-secure-media', $xml);
        $this->assertStringContainsString('true', $xml);
    }

    public function test_render_includes_rtp_secure_media_optional_for_optional_srtp(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'srtp_mode' => 'optional',
            'is_active' => true,
        ]);

        $service = new GatewayProvisioningService($this->noOpFreeSwitch());
        $xml = $service->render($gateway);

        $this->assertStringContainsString('rtp-secure-media', $xml);
        $this->assertStringContainsString('optional', $xml);
    }

    public function test_render_omits_rtp_secure_media_when_srtp_is_none(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'srtp_mode' => 'none',
            'is_active' => true,
        ]);

        $service = new GatewayProvisioningService($this->noOpFreeSwitch());
        $xml = $service->render($gateway);

        $this->assertStringNotContainsString('rtp-secure-media', $xml);
    }

    public function test_render_includes_dtmf_type_when_not_rfc2833(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'dtmf_mode' => 'info',
            'is_active' => true,
        ]);

        $service = new GatewayProvisioningService($this->noOpFreeSwitch());
        $xml = $service->render($gateway);

        $this->assertStringContainsString('dtmf-type', $xml);
        $this->assertStringContainsString('info', $xml);
    }

    public function test_render_omits_dtmf_type_when_rfc2833(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create([
            'tenant_id' => $tenant->id,
            'dtmf_mode' => 'rfc2833',
            'is_active' => true,
        ]);

        $service = new GatewayProvisioningService($this->noOpFreeSwitch());
        $xml = $service->render($gateway);

        $this->assertStringNotContainsString('dtmf-type', $xml);
    }
}
