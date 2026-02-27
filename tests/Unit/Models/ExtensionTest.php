<?php

namespace Tests\Unit\Models;

use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
        ]);
    }

    public function test_can_be_created_with_valid_attributes(): void
    {
        $tenant = $this->createTenant();

        $extension = $tenant->extensions()->create([
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
            'tenant_id' => $tenant->id,
        ]);
        $this->assertNotNull($extension->id);
    }

    public function test_belongs_to_a_tenant(): void
    {
        $tenant = $this->createTenant();

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $this->assertInstanceOf(Tenant::class, $extension->tenant);
        $this->assertEquals($tenant->id, $extension->tenant->id);
    }

    public function test_password_field_is_hidden(): void
    {
        $tenant = $this->createTenant();

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $array = $extension->toArray();
        $this->assertArrayNotHasKey('password', $array);
    }

    public function test_voicemail_pin_field_is_hidden(): void
    {
        $tenant = $this->createTenant();

        $extension = $tenant->extensions()->create([
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
        $tenant = $this->createTenant();

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'voicemail_enabled' => 1,
        ]);

        $extension->refresh();
        $this->assertIsBool($extension->voicemail_enabled);
    }
}
