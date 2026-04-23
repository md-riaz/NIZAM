<?php

namespace Tests\Unit\Observers;

use App\Models\DeviceProfile;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_extension_password_touches_device_profiles(): void
    {
        $organization = Organization::factory()->create();
        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'original-password',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $profile = DeviceProfile::create([
            'organization_id' => $organization->id,
            'name' => 'Test Phone',
            'vendor' => 'yealink',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $originalUpdatedAt = $profile->updated_at;

        $this->travel(5)->seconds();

        $extension->update(['password' => 'new-password']);

        $profile->refresh();
        $this->assertGreaterThan($originalUpdatedAt, $profile->updated_at);
    }

    public function test_updating_non_provisioning_fields_does_not_touch_profiles(): void
    {
        $organization = Organization::factory()->create();
        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'test-password',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $profile = DeviceProfile::create([
            'organization_id' => $organization->id,
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
        $organization = Organization::factory()->create();
        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'test-password',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $profile = DeviceProfile::create([
            'organization_id' => $organization->id,
            'name' => 'Test Phone',
            'vendor' => 'yealink',
            'mac_address' => 'AA:BB:CC:DD:EE:02',
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $originalUpdatedAt = $profile->updated_at;

        $this->travel(5)->seconds();

        $extension->update(['first_name' => 'Jane']);

        $profile->refresh();
        $this->assertGreaterThan($originalUpdatedAt, $profile->updated_at);
    }

    public function test_updating_default_outbound_did_touches_device_profiles(): void
    {
        $organization = Organization::factory()->create();
        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'test-password',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $profile = DeviceProfile::create([
            'organization_id' => $organization->id,
            'name' => 'Test Phone',
            'vendor' => 'yealink',
            'mac_address' => 'AA:BB:CC:DD:EE:03',
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15551234567',
            'description' => 'Main line',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);
        $extension->allowedOutboundDids()->attach($did->id);

        $originalUpdatedAt = $profile->updated_at;

        $this->travel(5)->seconds();

        $extension->update(['default_outbound_did_id' => $did->id]);

        $profile->refresh();
        $this->assertGreaterThan($originalUpdatedAt, $profile->updated_at);
    }
}
