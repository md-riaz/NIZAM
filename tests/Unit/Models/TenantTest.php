<?php

namespace Tests\Unit\Models;

use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_valid_attributes(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'max_extensions' => 50,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
        ]);
        $this->assertNotNull($tenant->id);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        $tenant = new Tenant;

        $this->assertEquals([
            'name',
            'domain',
            'slug',
            'settings',
            'codec_policy',
            'max_extensions',
            'max_concurrent_calls',
            'max_dids',
            'max_ring_groups',
            'is_active',
            'status',
        ], $tenant->getFillable());
    }

    public function test_has_many_extensions(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $this->assertCount(1, $tenant->extensions);
        $this->assertInstanceOf(Extension::class, $tenant->extensions->first());
    }

    public function test_has_many_dids(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $tenant->dids());
    }

    public function test_has_many_ring_groups(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $tenant->ringGroups());
    }

    public function test_has_many_ivrs(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $tenant->ivrs());
    }

    public function test_has_many_time_conditions(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $tenant->timeConditions());
    }

    public function test_has_many_cdrs(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $tenant->cdrs());
    }

    public function test_settings_is_cast_to_array(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'settings' => ['key' => 'value'],
        ]);

        $tenant->refresh();
        $this->assertIsArray($tenant->settings);
        $this->assertEquals(['key' => 'value'], $tenant->settings);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => 1,
        ]);

        $tenant->refresh();
        $this->assertIsBool($tenant->is_active);
        $this->assertTrue($tenant->is_active);
    }

    public function test_has_many_users(): void
    {
        $tenant = Tenant::factory()->create();
        \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertCount(1, $tenant->users);
        $this->assertInstanceOf(\App\Models\User::class, $tenant->users->first());
    }

    public function test_has_many_webhooks(): void
    {
        $tenant = Tenant::factory()->create();
        \App\Models\Webhook::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertCount(1, $tenant->webhooks);
        $this->assertInstanceOf(\App\Models\Webhook::class, $tenant->webhooks->first());
    }

    public function test_has_many_device_profiles(): void
    {
        $tenant = Tenant::factory()->create();
        \App\Models\DeviceProfile::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertCount(1, $tenant->deviceProfiles);
        $this->assertInstanceOf(\App\Models\DeviceProfile::class, $tenant->deviceProfiles->first());
    }
}
