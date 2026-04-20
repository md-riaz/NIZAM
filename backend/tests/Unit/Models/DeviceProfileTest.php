<?php

namespace Tests\Unit\Models;

use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_can_be_created_with_valid_attributes(): void
    {
        $organization = Organization::factory()->create();
        $profile = DeviceProfile::factory()->create(['organization_id' => $organization->id]);

        $this->assertDatabaseHas('device_profiles', [
            'id' => $profile->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_belongs_to_a_organization(): void
    {
        $organization = Organization::factory()->create();
        $profile = DeviceProfile::factory()->create(['organization_id' => $organization->id]);

        $this->assertInstanceOf(Organization::class, $profile->organization);
        $this->assertEquals($organization->id, $profile->organization->id);
    }

    public function test_belongs_to_an_extension(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $profile = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
        ]);

        $this->assertInstanceOf(Extension::class, $profile->extension);
        $this->assertEquals($extension->id, $profile->extension->id);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $profile = DeviceProfile::factory()->create(['is_active' => 1]);

        $this->assertIsBool($profile->is_active);
        $this->assertTrue($profile->is_active);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        $profile = new DeviceProfile;
        $expected = ['organization_id', 'user_id', 'name', 'vendor', 'mac_address', 'template', 'extension_id', 'is_active'];

        $this->assertEquals($expected, $profile->getFillable());
    }

    public function test_extension_is_nullable(): void
    {
        $profile = DeviceProfile::factory()->create(['extension_id' => null]);

        $this->assertNull($profile->extension);
    }

    public function test_can_optionally_belong_to_a_user(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $profile = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($profile->user->is($user));
        $this->assertTrue($profile->resolvedUser()->is($user));
    }

    public function test_can_resolve_user_through_extension_mapping(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
        $profile = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => null,
            'extension_id' => $extension->id,
        ]);

        $this->assertNull($profile->user);
        $this->assertTrue($profile->extension->is($extension));
        $this->assertTrue($profile->resolvedUser()->is($user));
    }
}
