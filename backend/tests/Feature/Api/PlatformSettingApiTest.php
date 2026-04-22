<?php

namespace Tests\Feature\Api;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_superadmin_can_view_platform_settings(): void
    {
        SystemSetting::upsertPlatformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, 'example.test');
        SystemSetting::upsertPlatformInteger(SystemSetting::EXTENSION_RANGE_START, 101);
        SystemSetting::upsertPlatformInteger(SystemSetting::EXTENSION_RANGE_END, 500);
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/platform-settings');

        $response->assertOk()
            ->assertJsonPath('data.organization_domain_suffix', 'example.test')
            ->assertJsonPath('data.extension_range_start', 101)
            ->assertJsonPath('data.extension_range_end', 500);
    }

    public function test_platform_superadmin_can_update_platform_settings(): void
    {
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/admin/platform-settings', [
            'organization_domain_suffix' => '.Org.Example.Com.',
            'extension_range_start' => 101,
            'extension_range_end' => 500,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.organization_domain_suffix', 'org.example.com')
            ->assertJsonPath('data.extension_range_start', 101)
            ->assertJsonPath('data.extension_range_end', 500);

        $this->assertSame(
            'org.example.com',
            SystemSetting::platformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX)
        );
        $this->assertSame(101, SystemSetting::platformInteger(SystemSetting::EXTENSION_RANGE_START));
        $this->assertSame(500, SystemSetting::platformInteger(SystemSetting::EXTENSION_RANGE_END));
    }

    public function test_organization_admin_cannot_view_platform_settings(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/platform-settings');

        $response->assertForbidden();
    }
}
