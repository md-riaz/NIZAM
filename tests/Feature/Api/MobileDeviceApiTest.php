<?php

namespace Tests\Feature\Api;

use App\Models\DeviceProfile;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_register_a_mobile_device_for_a_tenant(): void
    {
        $extension = Extension::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/register", [
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
            'tenant_id' => $this->tenant->id,
            'extension_id' => $extension->id,
            'device_uuid' => 'device-123',
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'is_push_capable' => true,
        ]);
    }

    public function test_register_reuses_existing_mobile_device_binding_for_same_device_uuid(): void
    {
        $extension = Extension::factory()->create(['tenant_id' => $this->tenant->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'tenant_id' => $this->tenant->id,
            'device_uuid' => 'device-123',
            'push_token' => 'old-token',
            'voip_push_token' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/register", [
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
        $extension = Extension::factory()->create(['tenant_id' => $this->tenant->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'tenant_id' => $this->tenant->id,
            'push_token' => 'old-token',
            'voip_push_token' => 'old-voip-token',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/{$binding->id}/refresh-token", [
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
        $extension = Extension::factory()->create(['tenant_id' => $this->tenant->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'tenant_id' => $this->tenant->id,
            'push_token' => 'old-token',
            'voip_push_token' => null,
            'metadata' => ['push_enabled' => false],
            'is_push_capable' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/{$binding->id}/refresh-token", [
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
        $extension = Extension::factory()->create(['tenant_id' => $this->tenant->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'tenant_id' => $this->tenant->id,
            'last_seen_at' => null,
        ]);
        $profile = DeviceProfile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'extension_id' => $extension->id,
            'name' => 'Provisioned Desk Set',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/{$binding->id}/heartbeat", [
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
        $extension = Extension::factory()->create(['tenant_id' => $this->tenant->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'tenant_id' => $this->tenant->id,
            'allow_late_join_after_push' => false,
            'rings_immediately_when_online' => true,
            'metadata' => ['push_enabled' => true, 'sip_background_mode_supported' => true],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/{$binding->id}/capabilities", [
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
        $extension = Extension::factory()->create(['tenant_id' => $this->tenant->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'tenant_id' => $this->tenant->id,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/{$binding->id}", [
                'is_enabled' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_enabled', false);

        $binding->refresh();

        $this->assertFalse($binding->isEligibleForOrchestration());
    }

    public function test_returns_403_when_user_targets_another_tenant_mobile_device_route(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $binding = EndpointBinding::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/{$binding->id}/heartbeat", [
                'app_version' => '9.9.9',
            ]);

        $response->assertStatus(403);
    }

    public function test_can_delete_a_tenant_scoped_mobile_device_binding(): void
    {
        $extension = Extension::factory()->create(['tenant_id' => $this->tenant->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'tenant_id' => $this->tenant->id,
            'type' => EndpointBinding::TYPE_MOBILE_APP,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/{$binding->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('endpoint_bindings', [
            'id' => $binding->id,
        ]);
    }

    public function test_returns_404_for_mobile_device_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $extension = Extension::factory()->create(['tenant_id' => $otherTenant->id]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/mobile-devices/{$binding->id}");

        $response->assertStatus(404);
    }
}
