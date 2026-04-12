<?php

namespace Tests\Feature\Api\Admin;

use App\Models\SipProfile;
use App\Models\SipProfileSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCapabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/admin/capabilities');

        $response->assertStatus(401);
    }

    public function test_multi_registration_is_inactive_when_setting_absent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/capabilities');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'status', 'category'],
            ],
        ]);
        $response->assertJsonFragment([
            'id' => 'multi_registration',
            'status' => 'inactive',
        ]);
    }

    public function test_multi_registration_is_inactive_when_setting_exists_but_is_enabled_false(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $internalProfile = SipProfile::create([
            'name' => 'internal',
            'is_active' => true,
        ]);

        SipProfileSetting::create([
            'sip_profile_id' => $internalProfile->id,
            'name' => 'multiple-registrations',
            'value' => 'contact',
            'is_enabled' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/capabilities');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => 'multi_registration',
            'status' => 'inactive',
        ]);
    }

    public function test_multi_registration_is_active_when_internal_profile_setting_exists_with_value_contact_and_is_enabled_true(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $internalProfile = SipProfile::create([
            'name' => 'internal',
            'is_active' => true,
        ]);

        SipProfileSetting::create([
            'sip_profile_id' => $internalProfile->id,
            'name' => 'multiple-registrations',
            'value' => 'contact',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/capabilities');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => 'multi_registration',
            'status' => 'active',
        ]);
    }

    public function test_non_admin_cannot_get_platform_capabilities(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/capabilities');

        $response->assertStatus(403);
    }
}
