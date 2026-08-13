<?php

namespace Tests\Feature\Api;

use App\Models\DeviceProfile;
use App\Models\Did;
use App\Models\Gateway;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtensionOutboundPolicySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_allowed_outbound_policy_rebuilds_manifest_and_touches_device_profiles(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        // Permissions are deny-by-default, so the acting user is granted the
        // extension ability this case exercises rather than relying on a
        // permissive fallback.
        Permission::updateOrCreate(['slug' => 'extensions.update'], ['module' => 'core']);
        $user->grantPermissions(['extensions.update']);

        $gateway = Gateway::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'is_active' => true,
        ]);
        $extension = $organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);
        $profile = DeviceProfile::create([
            'organization_id' => $organization->id,
            'name' => 'Desk Phone',
            'vendor' => 'yealink',
            'mac_address' => 'AA:BB:CC:DD:EE:11',
            'extension_id' => $extension->id,
            'is_active' => true,
        ]);

        $originalUpdatedAt = $profile->updated_at;

        $this->travel(5)->seconds();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/extensions/{$extension->id}", [
                'extension' => '101',
                'password' => 'secret1234',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'default_outbound_did_id' => $did->id,
                'allowed_outbound_did_ids' => [$did->id],
                'default_outbound_gateway_id' => $gateway->id,
                'allowed_outbound_gateway_ids' => [$gateway->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.default_outbound_did_id', $did->id)
            ->assertJsonPath('data.default_outbound_gateway_id', $gateway->id)
            ->assertJsonPath('data.allowed_outbound_did_ids.0', $did->id)
            ->assertJsonPath('data.allowed_outbound_gateway_ids.0', $gateway->id);

        $profile->refresh();
        $this->assertGreaterThan($originalUpdatedAt, $profile->updated_at);
    }

    public function test_user_without_permission_cannot_update_outbound_policy(): void
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);
        $unprivileged = User::factory()->create(['organization_id' => $organization->id]);
        $extension = $organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $this->actingAs($unprivileged, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/extensions/{$extension->id}", [
                'extension' => '101',
                'password' => 'secret1234',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ])
            ->assertForbidden();
    }
}
