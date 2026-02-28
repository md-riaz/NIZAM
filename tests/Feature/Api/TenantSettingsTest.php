<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create([
            'settings' => ['timezone' => 'UTC', 'language' => 'en'],
        ]);
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
    }

    public function test_can_get_tenant_settings(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/settings");

        $response->assertStatus(200);
        $response->assertJsonFragment(['timezone' => 'UTC', 'language' => 'en']);
    }

    public function test_can_update_tenant_settings_with_merge(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/settings", [
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
            'tenant_id' => $this->tenant->id,
            'role' => 'user',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/settings", [
                'settings' => ['language' => 'de'],
            ]);

        $response->assertStatus(403);
    }
}
