<?php

namespace Tests\Unit\Models;

use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_can_be_created_with_factory(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    public function test_belongs_to_a_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->assertInstanceOf(Organization::class, $user->organization);
        $this->assertEquals($organization->id, $user->organization->id);
    }

    public function test_organization_is_nullable(): void
    {
        $user = User::factory()->create(['organization_id' => null]);

        $this->assertNull($user->organization);
    }

    public function test_password_is_hidden(): void
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_remember_token_is_hidden(): void
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_has_correct_fillable_attributes(): void
    {
        $user = new User;
        $expected = ['name', 'email', 'password', 'organization_id', 'role'];

        $this->assertEquals($expected, $user->getFillable());
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'plaintext-password',
        ]);

        $this->assertNotEquals('plaintext-password', $user->getAttributes()['password']);
    }

    public function test_has_extensions_and_device_profiles_relationships(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);
        $deviceProfile = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($user->extensions->first()->is($extension));
        $this->assertTrue($user->primaryExtension->is($extension));
        $this->assertTrue($user->deviceProfiles->first()->is($deviceProfile));
    }
}
