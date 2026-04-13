<?php

namespace Tests\Feature\Api;

use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_tenant_admin_can_update_extension_features(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}/extensions/{$extension->id}/features", [
                'follow_me_enabled' => true,
                'follow_me_destination' => '+8801712345678',
                'dnd_enabled' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $extension->id)
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.follow_me_enabled', true)
            ->assertJsonPath('data.follow_me_destination', '+8801712345678')
            ->assertJsonPath('data.dnd_enabled', false);

        $this->assertDatabaseHas('extensions', [
            'id' => $extension->id,
            'tenant_id' => $tenant->id,
            'follow_me_enabled' => true,
            'follow_me_destination' => '+8801712345678',
            'dnd_enabled' => false,
        ]);
    }

    public function test_it_returns_validation_error_when_follow_me_enabled_without_destination(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}/extensions/{$extension->id}/features", [
                'follow_me_enabled' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['follow_me_destination']);
    }

    public function test_it_returns_not_found_for_extension_outside_tenant_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}/extensions/{$extension->id}/features", [
                'dnd_enabled' => true,
            ]);

        $response->assertNotFound();
    }

    public function test_it_applies_service_precedence_when_dnd_is_enabled(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'follow_me_enabled' => true,
            'follow_me_destination' => '+8801712345678',
            'dnd_enabled' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}/extensions/{$extension->id}/features", [
                'dnd_enabled' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.follow_me_enabled', false)
            ->assertJsonPath('data.follow_me_destination', null)
            ->assertJsonPath('data.dnd_enabled', true);
    }

    public function test_it_accepts_combined_follow_me_and_dnd_request_by_normalizing_to_dnd_precedence(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}/extensions/{$extension->id}/features", [
                'follow_me_enabled' => true,
                'dnd_enabled' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.follow_me_enabled', false)
            ->assertJsonPath('data.follow_me_destination', null)
            ->assertJsonPath('data.dnd_enabled', true);
    }

}
