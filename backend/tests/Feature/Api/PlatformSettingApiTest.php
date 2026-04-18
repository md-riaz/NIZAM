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
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/platform-settings');

        $response->assertOk()
            ->assertJsonPath('data.organization_domain_suffix', 'example.test');
    }

    public function test_platform_superadmin_can_update_platform_settings(): void
    {
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/admin/platform-settings', [
            'organization_domain_suffix' => '.Org.Example.Com.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.organization_domain_suffix', 'org.example.com');

        $this->assertSame(
            'org.example.com',
            SystemSetting::platformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX)
        );
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
