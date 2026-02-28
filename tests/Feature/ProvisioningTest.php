<?php

namespace Tests\Feature;

use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_provisioning_config_for_known_mac_address(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'extension_id' => $extension->id,
            'vendor' => 'yealink',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'is_active' => true,
            'template' => null,
        ]);

        $response = $this->get('/provision/AA:BB:CC:DD:EE:FF');

        $response->assertStatus(200);
        $this->assertStringContainsString('1001', $response->getContent());
    }

    public function test_returns_404_for_unknown_mac_address(): void
    {
        $response = $this->get('/provision/00:00:00:00:00:00');

        $response->assertStatus(404);
    }
}
