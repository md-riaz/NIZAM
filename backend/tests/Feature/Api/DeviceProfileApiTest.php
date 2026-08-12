<?php

namespace Tests\Feature\Api;

use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        // Permissions are deny-by-default, so the acting user is granted the
        // device profile abilities these cases exercise rather than relying on
        // a permissive fallback.
        $slugs = ['device_profiles.view', 'device_profiles.create', 'device_profiles.update', 'device_profiles.delete'];
        foreach ($slugs as $slug) {
            Permission::updateOrCreate(['slug' => $slug], ['module' => 'core']);
        }
        $this->user->grantPermissions($slugs);
    }

    public function test_user_without_permission_cannot_list_device_profiles(): void
    {
        $unprivileged = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/device-profiles")
            ->assertForbidden();
    }

    public function test_can_list_device_profiles_for_a_organization(): void
    {
        DeviceProfile::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/device-profiles");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_device_profile(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/device-profiles", [
                'name' => 'Lobby Phone',
                'vendor' => 'polycom',
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('device_profiles', [
            'organization_id' => $this->organization->id,
            'name' => 'Lobby Phone',
            'vendor' => 'polycom',
        ]);
    }

    public function test_can_show_a_device_profile(): void
    {
        $profile = DeviceProfile::factory()->create(['organization_id' => $this->organization->id]);
        $ownedExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'device_profile_id' => $profile->id,
            'extension' => '101',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/device-profiles/{$profile->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $profile->name]);
        $response->assertJsonPath('data.owned_extension_ids.0', $ownedExtension->id);
    }

    public function test_can_update_a_device_profile(): void
    {
        $profile = DeviceProfile::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/device-profiles/{$profile->id}", [
                'name' => 'Updated Phone',
                'vendor' => 'yealink',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('device_profiles', [
            'id' => $profile->id,
            'name' => 'Updated Phone',
            'vendor' => 'yealink',
        ]);
    }

    public function test_can_delete_a_device_profile(): void
    {
        $profile = DeviceProfile::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/device-profiles/{$profile->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('device_profiles', ['id' => $profile->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/device-profiles", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'vendor']);
    }

    public function test_can_create_with_extension_assignment(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/device-profiles", [
                'name' => 'Desk Phone',
                'vendor' => 'grandstream',
                'mac_address' => '11:22:33:44:55:66',
                'extension_id' => $extension->id,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('device_profiles', [
            'extension_id' => $extension->id,
        ]);
    }

    public function test_returns_404_for_wrong_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $profile = DeviceProfile::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/device-profiles/{$profile->id}");

        $response->assertStatus(404);
    }
}
