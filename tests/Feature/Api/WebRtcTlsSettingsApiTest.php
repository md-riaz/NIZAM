<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WebRtcTlsSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebRtcTlsSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_webrtc_tls_settings(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/webrtc-tls');

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.active_mode', 'trusted_ca');
        $response->assertJsonStructure([
            'data' => [
                'webrtc_enabled',
                'active_mode',
                'modes' => [
                    'trusted_ca' => ['key', 'label', 'enabled', 'cert_dir', 'production_ready', 'summary', 'details', 'expected_files'],
                    'self_signed' => ['key', 'label', 'enabled', 'cert_dir', 'production_ready', 'summary', 'details', 'expected_files'],
                ],
            ],
        ]);
    }

    public function test_platform_admin_can_update_webrtc_tls_settings(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        $payload = [
            'webrtc_enabled' => true,
            'active_mode' => 'self_signed',
            'trusted_ca_enabled' => true,
            'trusted_ca_cert_dir' => '/secure/certs/trusted',
            'self_signed_enabled' => true,
            'self_signed_cert_dir' => '/secure/certs/dev',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/admin/webrtc-tls', $payload);

        $response->assertOk();
        $response->assertJsonPath('data.active_mode', 'self_signed');
        $response->assertJsonPath('data.modes.self_signed.cert_dir', '/secure/certs/dev');

        $this->assertDatabaseHas('webrtc_tls_settings', [
            'webrtc_enabled' => true,
            'active_mode' => 'self_signed',
            'trusted_ca_cert_dir' => '/secure/certs/trusted',
            'self_signed_cert_dir' => '/secure/certs/dev',
        ]);
    }

    public function test_platform_admin_cannot_disable_both_modes(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/admin/webrtc-tls', [
                'webrtc_enabled' => true,
                'active_mode' => 'trusted_ca',
                'trusted_ca_enabled' => false,
                'trusted_ca_cert_dir' => '/secure/certs/trusted',
                'self_signed_enabled' => false,
                'self_signed_cert_dir' => '/secure/certs/dev',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trusted_ca_enabled', 'self_signed_enabled']);
    }

    public function test_tenant_admin_cannot_access_webrtc_tls_settings(): void
    {
        $tenant = \App\Models\Tenant::query()->create([
            'name' => 'Tenant Admin Scope',
            'domain' => 'tenant-admin.example.com',
            'slug' => 'tenant-admin-scope',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/webrtc-tls');

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_webrtc_tls_settings(): void
    {
        $response = $this->getJson('/api/v1/admin/webrtc-tls');

        $response->assertUnauthorized();
    }
}
