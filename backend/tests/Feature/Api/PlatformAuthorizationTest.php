<?php

namespace Tests\Feature\Api;

use App\Models\Gateway;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\SipProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the platform-level routes that cross organization boundaries.
 *
 * SIP profiles are global FreeSWITCH objects and admin/gateways deliberately
 * lists every tenant, so both must be reachable only by platform admins.
 */
class PlatformAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
    }

    private function tenantAdmin(Organization $organization): User
    {
        return User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
    }

    private function tenantAgent(Organization $organization): User
    {
        return User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);
    }

    public function test_tenant_admin_cannot_read_sip_profiles(): void
    {
        $organization = Organization::factory()->create();

        $this->actingAs($this->tenantAdmin($organization), 'sanctum')
            ->getJson('/api/v1/admin/sip-profiles')
            ->assertForbidden();
    }

    public function test_tenant_agent_cannot_read_sip_profiles(): void
    {
        $organization = Organization::factory()->create();

        $this->actingAs($this->tenantAgent($organization), 'sanctum')
            ->getJson('/api/v1/admin/sip-profiles')
            ->assertForbidden();
    }

    public function test_tenant_admin_cannot_create_sip_profile(): void
    {
        $organization = Organization::factory()->create();

        $this->actingAs($this->tenantAdmin($organization), 'sanctum')
            ->postJson('/api/v1/admin/sip-profiles', ['name' => 'malicious'])
            ->assertForbidden();

        $this->assertDatabaseMissing('sip_profiles', ['name' => 'malicious']);
    }

    public function test_tenant_admin_cannot_update_or_delete_sip_profile(): void
    {
        $organization = Organization::factory()->create();
        $profile = SipProfile::create(['name' => 'internal', 'is_active' => true]);
        $actor = $this->tenantAdmin($organization);

        $this->actingAs($actor, 'sanctum')
            ->putJson("/api/v1/admin/sip-profiles/{$profile->id}", ['name' => 'hijacked'])
            ->assertForbidden();

        $this->actingAs($actor, 'sanctum')
            ->getJson("/api/v1/admin/sip-profiles/{$profile->id}")
            ->assertForbidden();

        $this->actingAs($actor, 'sanctum')
            ->deleteJson("/api/v1/admin/sip-profiles/{$profile->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('sip_profiles', ['id' => $profile->id, 'name' => 'internal']);
    }

    public function test_platform_admin_can_read_sip_profiles(): void
    {
        SipProfile::create(['name' => 'internal', 'is_active' => true]);

        $this->actingAs($this->platformAdmin(), 'sanctum')
            ->getJson('/api/v1/admin/sip-profiles')
            ->assertOk();
    }

    public function test_tenant_user_cannot_enumerate_other_organizations_gateways(): void
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();
        Gateway::factory()->create(['organization_id' => $theirs->id]);

        // Explicitly grant the slug the policy checks, to prove the platform
        // gate is what denies this rather than a missing permission.
        $actor = $this->tenantAgent($mine);
        Permission::updateOrCreate(['slug' => 'gateways.view'], ['module' => 'module']);
        $actor->grantPermissions(['gateways.view']);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/v1/admin/gateways')
            ->assertForbidden();
    }

    public function test_platform_admin_can_list_all_gateways(): void
    {
        $organization = Organization::factory()->create();
        Gateway::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($this->platformAdmin(), 'sanctum')
            ->getJson('/api/v1/admin/gateways')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
