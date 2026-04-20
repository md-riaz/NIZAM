<?php

namespace Tests\Unit\Services;

use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\ProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->service = new ProvisioningService;
    }

    public function test_renders_config_with_extension_variables_substituted(): void
    {
        $organization = Organization::factory()->create(['domain' => 'test.example.com']);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $profile = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
            'vendor' => 'yealink',
            'mac_address' => '00:11:22:33:44:55',
            'template' => 'user={{EXTENSION}} pass={{PASSWORD}} domain={{DOMAIN}} name={{DISPLAY_NAME}} strategy={{ENDPOINT_STRATEGY}} mode={{PROVISIONING_MODE}} transport={{SOFTPHONE_TRANSPORT}}',
        ]);

        $config = $this->service->renderConfig($profile);

        $this->assertStringContainsString('user=1001', $config);
        $this->assertStringContainsString('pass=secret1234', $config);
        $this->assertStringContainsString('domain=test.example.com', $config);
        $this->assertStringContainsString('name=John Doe', $config);
        $this->assertStringContainsString('strategy=softphone_first', $config);
        $this->assertStringContainsString('mode=optional_hardware', $config);
    }

    public function test_returns_default_template_when_device_has_no_custom_template(): void
    {
        $organization = Organization::factory()->create(['domain' => 'test.example.com']);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $profile = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
            'vendor' => 'yealink',
            'mac_address' => '00:11:22:33:44:55',
            'template' => null,
        ]);

        $config = $this->service->renderConfig($profile);

        $this->assertStringContainsString('account.1.enable = 1', $config);
        $this->assertStringContainsString('1001', $config);
        $this->assertStringContainsString('endpoint_strategy = softphone_first', $config);
        $this->assertStringContainsString('provisioning_mode = optional_hardware', $config);
    }

    public function test_reports_softphone_first_endpoint_strategy(): void
    {
        config([
            'app.url' => 'https://portal.example.test',
            'telephony.freeswitch.sip_port' => 5060,
            'telephony.freeswitch.external_sip_port' => 5061,
            'telephony.freeswitch.wss_port' => 7443,
        ]);

        $strategy = $this->service->endpointStrategy('organization-softphone.example.com');

        $this->assertSame('softphone', $strategy['default_endpoint']);
        $this->assertSame('optional', $strategy['hardware_provisioning']);
        $this->assertTrue($strategy['softphone']['recommended']);
        $this->assertFalse($strategy['hardware']['recommended']);
        $this->assertSame('organization-softphone.example.com:5060', $strategy['softphone']['sip_server']);
        $this->assertSame('wss://organization-softphone.example.com:7443', $strategy['softphone']['websocket_url']);
    }

    public function test_finds_device_by_mac_address_normalized(): void
    {
        $organization = Organization::factory()->create();
        $profile = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'mac_address' => '00:11:22:33:44:55',
            'is_active' => true,
        ]);

        // Search with different format (dashes instead of colons)
        $found = $this->service->findByMac('00-11-22-33-44-55');

        $this->assertNotNull($found);
        $this->assertEquals($profile->id, $found->id);
    }

    public function test_returns_null_for_unknown_mac(): void
    {
        $found = $this->service->findByMac('FF:FF:FF:FF:FF:FF');

        $this->assertNull($found);
    }
}
