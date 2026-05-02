<?php

namespace Tests\Unit\Models;

use App\Models\Extension;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
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
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'max_extensions' => 50,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);
        $this->assertNotNull($organization->id);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        $organization = new Organization;

        $this->assertEquals([
            'name',
            'domain',
            'default_schedule_id',
            'default_holiday_calendar_id',
            'settings',
            'codec_policy',
            'max_extensions',
            'max_concurrent_calls',
            'max_dids',
            'max_teams',
            'recording_retention_days',
            'max_calls_per_minute',
            'is_active',
            'status',
        ], $organization->getFillable());
    }

    public function test_has_many_extensions(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertCount(1, $organization->extensions);
        $this->assertInstanceOf(Extension::class, $organization->extensions->first());
    }

    public function test_has_many_dids(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $organization->dids());
    }

    public function test_has_many_ring_groups(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $organization->ringGroups());
    }

    public function test_has_many_ivrs(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $organization->ivrs());
    }

    public function test_has_many_time_conditions(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $organization->timeConditions());
    }

    public function test_has_many_cdrs(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $organization->cdrs());
    }

    public function test_settings_is_cast_to_array(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'settings' => ['key' => 'value'],
        ]);

        $organization->refresh();
        $this->assertIsArray($organization->settings);
        $this->assertEquals(['key' => 'value'], $organization->settings);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => 1,
        ]);

        $organization->refresh();
        $this->assertIsBool($organization->is_active);
        $this->assertTrue($organization->is_active);
    }

    public function test_has_many_users(): void
    {
        $organization = Organization::factory()->create();
        \App\Models\User::factory()->create(['organization_id' => $organization->id]);

        $this->assertCount(1, $organization->users);
        $this->assertInstanceOf(\App\Models\User::class, $organization->users->first());
    }

    public function test_has_many_webhooks(): void
    {
        $organization = Organization::factory()->create();
        \App\Models\Webhook::factory()->create(['organization_id' => $organization->id]);

        $this->assertCount(1, $organization->webhooks);
        $this->assertInstanceOf(\App\Models\Webhook::class, $organization->webhooks->first());
    }

    public function test_has_many_device_profiles(): void
    {
        $organization = Organization::factory()->create();
        \App\Models\DeviceProfile::factory()->create(['organization_id' => $organization->id]);

        $this->assertCount(1, $organization->deviceProfiles);
        $this->assertInstanceOf(\App\Models\DeviceProfile::class, $organization->deviceProfiles->first());
    }
}
