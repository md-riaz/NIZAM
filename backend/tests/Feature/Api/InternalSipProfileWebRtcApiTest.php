<?php

namespace Tests\Feature\Api;

use App\Models\SipProfile;
use App\Models\User;
use Database\Seeders\SipProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function PHPUnit\Framework\assertNotNull;

class InternalSipProfileWebRtcApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_update_internal_profile_webrtc_settings(): void
    {
        $this->seed(SipProfileSeeder::class);

        $user = User::factory()->create(['role' => 'admin', 'organization_id' => null]);
        $profile = SipProfile::query()->where('name', 'internal')->first();
        assertNotNull($profile);

        $payload = [
            'settings' => [
                ['name' => 'wss-binding', 'value' => ':7443', 'is_enabled' => true],
                ['name' => 'tls-cert-dir', 'value' => '/secure/certs/dev', 'is_enabled' => true],
                ['name' => 'dtls-srtp', 'value' => 'true', 'is_enabled' => true],
                ['name' => 'enable-ice', 'value' => 'true', 'is_enabled' => true],
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/admin/sip-profiles/'.$profile->id, $payload);

        $response->assertOk();
        $this->assertDatabaseHas('sip_profile_settings', [
            'sip_profile_id' => $profile->id,
            'name' => 'tls-cert-dir',
            'value' => '/secure/certs/dev',
            'is_enabled' => true,
        ]);
    }

    public function test_rejects_invalid_webrtc_binding_format(): void
    {
        $this->seed(SipProfileSeeder::class);

        $user = User::factory()->create(['role' => 'admin', 'organization_id' => null]);
        $profile = SipProfile::query()->where('name', 'internal')->first();
        assertNotNull($profile);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/admin/sip-profiles/'.$profile->id, [
                'settings' => [
                    ['name' => 'wss-binding', 'value' => '7443', 'is_enabled' => true],
                ],
            ]);

        $response->assertStatus(422);
    }
}
