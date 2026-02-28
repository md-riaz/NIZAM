<?php

namespace Tests\Unit\Observers;

use App\Models\DeviceProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_extension_password_touches_device_profiles(): void
    {
        $tenant = Tenant::factory()->create();
        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'original-password',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $profile = DeviceProfile::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Phone',
            'vendor' => 'yealink',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $originalUpdatedAt = $profile->updated_at;

        // Wait a moment to ensure timestamp difference
        sleep(1);

        $extension->update(['password' => 'new-password']);

        $profile->refresh();
        $this->assertGreaterThan($originalUpdatedAt, $profile->updated_at);
    }

    public function test_updating_non_provisioning_fields_does_not_touch_profiles(): void
    {
        $tenant = Tenant::factory()->create();
        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'test-password',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $profile = DeviceProfile::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Phone',
            'vendor' => 'yealink',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $originalUpdatedAt = $profile->updated_at->toDateTimeString();

        // Update a non-provisioning field
        $extension->update(['outbound_caller_id_name' => 'New CID']);

        $profile->refresh();
        $this->assertEquals($originalUpdatedAt, $profile->updated_at->toDateTimeString());
    }

    public function test_updating_extension_name_touches_device_profiles(): void
    {
        $tenant = Tenant::factory()->create();
        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'test-password',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $profile = DeviceProfile::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Phone',
            'vendor' => 'yealink',
            'mac_address' => 'AA:BB:CC:DD:EE:02',
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $originalUpdatedAt = $profile->updated_at;

        sleep(1);

        $extension->update(['directory_first_name' => 'Jane']);

        $profile->refresh();
        $this->assertGreaterThan($originalUpdatedAt, $profile->updated_at);
    }
}
