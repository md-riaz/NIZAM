<?php

namespace Tests\Unit\Models;

use App\Models\DeviceProfile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    private function createOrganization(): Organization
    {
        return Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);
    }

    public function test_can_be_created_with_valid_attributes(): void
    {
        $organization = $this->createOrganization();

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'effective_caller_id_name' => 'John Doe',
            'effective_caller_id_number' => '1001',
            'voicemail_enabled' => true,
            'voicemail_pin' => '1234',
        ]);

        $this->assertDatabaseHas('extensions', [
            'extension' => '1001',
            'directory_first_name' => 'John',
            'organization_id' => $organization->id,
        ]);
        $this->assertNotNull($extension->id);
    }

    public function test_belongs_to_a_organization(): void
    {
        $organization = $this->createOrganization();

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $this->assertInstanceOf(Organization::class, $extension->organization);
        $this->assertEquals($organization->id, $extension->organization->id);
    }

    public function test_password_field_is_hidden_from_serialization(): void
    {
        $organization = $this->createOrganization();

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $array = $extension->toArray();
        $this->assertArrayNotHasKey('password', $array);
    }

    public function test_voicemail_pin_field_is_hidden_from_serialization(): void
    {
        $organization = $this->createOrganization();

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'voicemail_pin' => '1234',
        ]);

        $array = $extension->toArray();
        $this->assertArrayNotHasKey('voicemail_pin', $array);
    }

    public function test_voicemail_enabled_is_cast_to_boolean(): void
    {
        $organization = $this->createOrganization();

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'voicemail_enabled' => 1,
        ]);

        $extension->refresh();
        $this->assertIsBool($extension->voicemail_enabled);
    }

    public function test_password_is_encrypted_at_rest(): void
    {
        $organization = $this->createOrganization();

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        // The model should return the decrypted original value
        $this->assertEquals('secret1234', $extension->password);

        // The raw database value should NOT be plaintext (it should be encrypted)
        $rawValue = \Illuminate\Support\Facades\DB::table('extensions')
            ->where('id', $extension->id)
            ->value('password');
        $this->assertNotEquals('secret1234', $rawValue);
    }

    public function test_voicemail_pin_is_encrypted_at_rest(): void
    {
        $organization = $this->createOrganization();

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'voicemail_pin' => '5678',
        ]);

        // The model should return the decrypted original value
        $this->assertEquals('5678', $extension->voicemail_pin);

        // The raw database value should NOT be plaintext (it should be encrypted)
        $rawValue = \Illuminate\Support\Facades\DB::table('extensions')
            ->where('id', $extension->id)
            ->value('voicemail_pin');
        $this->assertNotEquals('5678', $rawValue);
    }

    public function test_extension_optionally_belongs_to_a_user_and_can_resolve_primary_device_profile(): void
    {
        $organization = $this->createOrganization();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $extension = $organization->extensions()->create([
            'user_id' => $user->id,
            'extension' => '1002',
            'password' => 'secret1234',
            'directory_first_name' => 'Jane',
            'directory_last_name' => 'Doe',
            'is_primary' => true,
        ]);

        $deviceProfile = DeviceProfile::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $this->assertTrue($extension->user->is($user));
        $this->assertTrue($extension->primaryDeviceProfile->is($deviceProfile));
        $this->assertTrue($extension->is_primary);
    }
}
