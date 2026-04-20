<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create([
            'settings' => ['timezone' => 'UTC', 'language' => 'en'],
        ]);
        $this->admin = User::factory()->create([
            'organization_id' => null,
            'role' => 'superadmin',
        ]);
    }

    public function test_can_get_organization_settings(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/settings");

        $response->assertStatus(200);
        $response->assertJsonFragment(['timezone' => 'UTC', 'language' => 'en']);
    }

    public function test_can_update_organization_settings_with_merge(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
                'settings' => ['language' => 'fr', 'recording_format' => 'mp3'],
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'timezone' => 'UTC',
            'language' => 'fr',
            'recording_format' => 'mp3',
        ]);
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'agent',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/settings", [
                'settings' => ['language' => 'de'],
            ]);

        $response->assertStatus(403);
    }
}
