<?php

namespace Tests\Unit\Models;

use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Tenant;
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
        $tenant = Tenant::factory()->create();
        $profile = DeviceProfile::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('device_profiles', [
            'id' => $profile->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_belongs_to_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $profile = DeviceProfile::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $profile->tenant);
        $this->assertEquals($tenant->id, $profile->tenant->id);
    }

    public function test_belongs_to_an_extension(): void
    {
        $tenant = Tenant::factory()->create();
        $extension = Extension::factory()->create(['tenant_id' => $tenant->id]);
        $profile = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
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
        $expected = ['tenant_id', 'user_id', 'name', 'vendor', 'mac_address', 'template', 'extension_id', 'is_active'];

        $this->assertEquals($expected, $profile->getFillable());
    }

    public function test_extension_is_nullable(): void
    {
        $profile = DeviceProfile::factory()->create(['extension_id' => null]);

        $this->assertNull($profile->extension);
    }

    public function test_can_optionally_belong_to_a_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $profile = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($profile->user->is($user));
        $this->assertTrue($profile->resolvedUser()->is($user));
    }

    public function test_can_resolve_user_through_extension_mapping(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
        $profile = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'extension_id' => $extension->id,
        ]);

        $this->assertNull($profile->user);
        $this->assertTrue($profile->extension->is($extension));
        $this->assertTrue($profile->resolvedUser()->is($user));
    }
}
