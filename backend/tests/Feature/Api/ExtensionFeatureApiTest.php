<?php

namespace Tests\Feature\Api;

use App\Models\Extension;
use App\Models\Organization;
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

    public function test_organization_admin_can_update_extension_features(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/extensions/{$extension->id}/features", [
                'follow_me_enabled' => true,
                'follow_me_destination' => '+8801712345678',
                'dnd_enabled' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $extension->id)
            ->assertJsonPath('data.organization_id', $organization->id)
            ->assertJsonPath('data.follow_me_enabled', true)
            ->assertJsonPath('data.follow_me_destination', '+8801712345678')
            ->assertJsonPath('data.dnd_enabled', false);

        $this->assertDatabaseHas('extensions', [
            'id' => $extension->id,
            'organization_id' => $organization->id,
            'follow_me_enabled' => true,
            'follow_me_destination' => '+8801712345678',
            'dnd_enabled' => false,
        ]);
    }

    public function test_it_returns_validation_error_when_follow_me_enabled_without_destination(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/extensions/{$extension->id}/features", [
                'follow_me_enabled' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['follow_me_destination']);
    }

    public function test_it_returns_not_found_for_extension_outside_organization_scope(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        $extension = Extension::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/extensions/{$extension->id}/features", [
                'dnd_enabled' => true,
            ]);

        $response->assertNotFound();
    }

    public function test_it_applies_service_precedence_when_dnd_is_enabled(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'follow_me_enabled' => true,
            'follow_me_destination' => '+8801712345678',
            'dnd_enabled' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/extensions/{$extension->id}/features", [
                'dnd_enabled' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.follow_me_enabled', false)
            ->assertJsonPath('data.follow_me_destination', null)
            ->assertJsonPath('data.dnd_enabled', true);
    }

    public function test_it_accepts_combined_follow_me_and_dnd_request_by_normalizing_to_dnd_precedence(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/extensions/{$extension->id}/features", [
                'follow_me_enabled' => true,
                'dnd_enabled' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.follow_me_enabled', false)
            ->assertJsonPath('data.follow_me_destination', null)
            ->assertJsonPath('data.dnd_enabled', true);
    }

}
