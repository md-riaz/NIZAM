<?php

namespace Tests\Feature\Api;

use App\Models\DeviceProfile;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        // Permissions are deny-by-default, so the acting user is granted the
        // endpoint-binding abilities these cases exercise rather than relying
        // on a permissive fallback.
        $slugs = ['endpoint_bindings.create', 'endpoint_bindings.update', 'endpoint_bindings.delete'];
        foreach ($slugs as $slug) {
            Permission::updateOrCreate(['slug' => $slug], ['module' => 'core']);
        }
        $this->user->grantPermissions($slugs);
    }

    public function test_user_without_permission_cannot_register_a_mobile_device(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $unprivileged = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($unprivileged, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/register", [
                'extension_id' => $extension->id,
                'device_uuid' => 'device-456',
                'platform' => EndpointBinding::PLATFORM_IOS,
                'push_token' => 'push-token',
                'app_version' => '1.0.0',
            ])
            ->assertForbidden();
    }

    public function test_can_register_a_mobile_device_for_a_organization(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/register", [
                'extension_id' => $extension->id,
                'device_uuid' => 'device-123',
                'platform' => EndpointBinding::PLATFORM_IOS,
                'push_token' => 'push-token',
                'voip_push_token' => 'voip-token',
                'app_version' => '1.0.0',
                'push_enabled' => true,
                'sip_background_mode_supported' => true,
                'allow_late_join_after_push' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.device_uuid', 'device-123')
            ->assertJsonPath('data.push_enabled', true)
            ->assertJsonPath('data.sip_background_mode_supported', true);

        $this->assertDatabaseHas('endpoint_bindings', [
            'organization_id' => $this->organization->id,
            'extension_id' => $extension->id,
            'device_uuid' => 'device-123',
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'is_push_capable' => true,
        ]);
    }

    public function test_register_reuses_existing_mobile_device_binding_for_same_device_uuid(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'organization_id' => $this->organization->id,
            'device_uuid' => 'device-123',
            'push_token' => 'old-token',
            'voip_push_token' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/register", [
                'extension_id' => $extension->id,
                'device_uuid' => 'device-123',
                'platform' => EndpointBinding::PLATFORM_ANDROID,
                'push_token' => 'new-token',
                'app_version' => '2.0.0',
                'push_enabled' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $binding->id)
            ->assertJsonPath('data.platform', EndpointBinding::PLATFORM_ANDROID);

        $this->assertDatabaseHas('endpoint_bindings', [
            'id' => $binding->id,
            'push_token' => 'new-token',
            'platform' => EndpointBinding::PLATFORM_ANDROID,
        ]);
        $this->assertDatabaseCount('endpoint_bindings', 1);
    }

    public function test_refresh_token_rotates_token_without_creating_new_binding(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'organization_id' => $this->organization->id,
            'push_token' => 'old-token',
            'voip_push_token' => 'old-voip-token',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/{$binding->id}/refresh-token", [
                'push_token' => 'new-token',
                'voip_push_token' => 'new-voip-token',
                'push_enabled' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $binding->id)
            ->assertJsonPath('data.has_voip_push_token', true);

        $this->assertDatabaseHas('endpoint_bindings', [
            'id' => $binding->id,
            'push_token' => 'new-token',
            'voip_push_token' => 'new-voip-token',
        ]);
        $this->assertDatabaseCount('endpoint_bindings', 1);
    }

    public function test_refresh_token_rejects_push_capable_update_without_token_material(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'organization_id' => $this->organization->id,
            'push_token' => 'old-token',
            'voip_push_token' => null,
            'metadata' => ['push_enabled' => false],
            'is_push_capable' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/{$binding->id}/refresh-token", [
                'push_token' => null,
                'voip_push_token' => null,
                'push_enabled' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['push_token']);

        $binding->refresh();

        $this->assertSame('old-token', $binding->push_token);
        $this->assertFalse($binding->pushEnabled());
    }

    public function test_heartbeat_updates_runtime_state_without_touching_device_profiles(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'organization_id' => $this->organization->id,
            'last_seen_at' => null,
        ]);
        $profile = DeviceProfile::factory()->create([
            'organization_id' => $this->organization->id,
            'extension_id' => $extension->id,
            'name' => 'Provisioned Desk Set',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/{$binding->id}/heartbeat", [
                'app_version' => '3.1.4',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.app_version', '3.1.4');

        $this->assertDatabaseHas('endpoint_bindings', [
            'id' => $binding->id,
            'app_version' => '3.1.4',
        ]);
        $this->assertDatabaseHas('device_profiles', [
            'id' => $profile->id,
            'name' => 'Provisioned Desk Set',
        ]);
    }

    public function test_capabilities_endpoint_updates_runtime_flags_for_mobile_binding(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'organization_id' => $this->organization->id,
            'allow_late_join_after_push' => false,
            'rings_immediately_when_online' => true,
            'metadata' => ['push_enabled' => true, 'sip_background_mode_supported' => true],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/{$binding->id}/capabilities", [
                'push_enabled' => false,
                'sip_background_mode_supported' => false,
                'allow_late_join_after_push' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.push_enabled', false)
            ->assertJsonPath('data.sip_background_mode_supported', false)
            ->assertJsonPath('data.allow_late_join_after_push', true);

        $this->assertDatabaseHas('endpoint_bindings', [
            'id' => $binding->id,
            'allow_late_join_after_push' => true,
            'rings_immediately_when_online' => false,
        ]);
    }

    public function test_disabled_mobile_device_is_marked_ineligible_for_orchestration(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'organization_id' => $this->organization->id,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/{$binding->id}", [
                'is_enabled' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_enabled', false);

        $binding->refresh();

        $this->assertFalse($binding->isEligibleForOrchestration());
    }

    public function test_returns_403_when_user_targets_another_organization_mobile_device_route(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrganization->id]);
        $binding = EndpointBinding::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/{$binding->id}/heartbeat", [
                'app_version' => '9.9.9',
            ]);

        $response->assertStatus(403);
    }

    public function test_can_delete_a_organization_scoped_mobile_device_binding(): void
    {
        $extension = Extension::factory()->create(['organization_id' => $this->organization->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'organization_id' => $this->organization->id,
            'type' => EndpointBinding::TYPE_MOBILE_APP,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/{$binding->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('endpoint_bindings', [
            'id' => $binding->id,
        ]);
    }

    public function test_returns_404_for_mobile_device_from_another_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $otherOrganization->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/mobile-devices/{$binding->id}");

        $response->assertStatus(404);
    }
}
