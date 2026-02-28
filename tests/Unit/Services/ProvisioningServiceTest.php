<?php

namespace Tests\Unit\Services;

use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Tenant;
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
        $this->service = new ProvisioningService;
    }

    public function test_renders_config_with_extension_variables_substituted(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $profile = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'vendor' => 'yealink',
            'mac_address' => '00:11:22:33:44:55',
            'template' => 'user={{EXTENSION}} pass={{PASSWORD}} domain={{DOMAIN}} name={{DISPLAY_NAME}}',
        ]);

        $config = $this->service->renderConfig($profile);

        $this->assertStringContainsString('user=1001', $config);
        $this->assertStringContainsString('pass=secret1234', $config);
        $this->assertStringContainsString('domain=test.example.com', $config);
        $this->assertStringContainsString('name=John Doe', $config);
    }

    public function test_returns_default_template_when_device_has_no_custom_template(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $profile = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'vendor' => 'yealink',
            'mac_address' => '00:11:22:33:44:55',
            'template' => null,
        ]);

        $config = $this->service->renderConfig($profile);

        $this->assertStringContainsString('account.1.enable = 1', $config);
        $this->assertStringContainsString('1001', $config);
    }

    public function test_finds_device_by_mac_address_normalized(): void
    {
        $tenant = Tenant::factory()->create();
        $profile = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
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
