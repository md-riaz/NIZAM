<?php

namespace Tests\Unit\Models;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the deny-by-default permission contract.
 *
 * hasPermission() was previously default-open: a user with no grants was
 * allowed everything, so a freshly created agent silently held every
 * permission in their organization.
 */
class UserPermissionDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_with_no_grants_is_denied(): void
    {
        $organization = Organization::factory()->create();
        $agent = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);

        $this->assertFalse($agent->hasPermission('recordings.view'));
        $this->assertFalse($agent->hasPermission('recordings.download'));
        $this->assertFalse($agent->hasPermission('extensions.update'));
    }

    public function test_agent_holds_only_explicitly_granted_permissions(): void
    {
        $organization = Organization::factory()->create();
        $agent = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);

        Permission::updateOrCreate(['slug' => 'extensions.view'], ['module' => 'core']);
        Permission::updateOrCreate(['slug' => 'recordings.view'], ['module' => 'core']);
        $agent->grantPermissions(['extensions.view']);

        $this->assertTrue($agent->hasPermission('extensions.view'));
        $this->assertFalse($agent->hasPermission('recordings.view'));
    }

    public function test_revoking_the_last_permission_does_not_widen_access(): void
    {
        $organization = Organization::factory()->create();
        $agent = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);

        Permission::updateOrCreate(['slug' => 'extensions.view'], ['module' => 'core']);
        $agent->grantPermissions(['extensions.view']);
        $agent->revokePermissions(['extensions.view']);

        $this->assertFalse($agent->fresh()->hasPermission('extensions.view'));
        $this->assertFalse($agent->fresh()->hasPermission('recordings.view'));
    }

    public function test_admins_and_superadmins_still_bypass_the_check(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        $superadmin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $this->assertTrue($admin->hasPermission('recordings.download'));
        $this->assertTrue($superadmin->hasPermission('recordings.download'));
    }

    public function test_role_baseline_excludes_other_peoples_sensitive_content(): void
    {
        $baseline = User::baselinePermissionsFor('agent');

        $this->assertNotEmpty($baseline);

        foreach (['recordings.view', 'recordings.download', 'recordings.delete', 'cdrs.view', 'cdrs.export', 'audit_logs.view'] as $sensitive) {
            $this->assertNotContains($sensitive, $baseline, "Agent baseline must not include {$sensitive}");
        }

        foreach ($baseline as $slug) {
            $this->assertDoesNotMatchRegularExpression(
                '/\.(create|update|delete|manage)$/',
                $slug,
                "Agent baseline must be read-only, found {$slug}",
            );
        }
    }

    public function test_granting_the_role_baseline_skips_slugs_that_do_not_exist(): void
    {
        $organization = Organization::factory()->create();
        $agent = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);

        // Only one baseline slug exists — e.g. a deployment with the contact
        // centre module disabled. Granting must not fail on the rest.
        Permission::updateOrCreate(['slug' => 'extensions.view'], ['module' => 'core']);

        $agent->grantRoleBaselinePermissions();

        $this->assertTrue($agent->fresh()->hasPermission('extensions.view'));
        $this->assertFalse($agent->fresh()->hasPermission('queues.view'));
    }

    public function test_admin_role_has_no_baseline_because_it_bypasses_checks(): void
    {
        $this->assertSame([], User::baselinePermissionsFor('admin'));
        $this->assertSame([], User::baselinePermissionsFor('superadmin'));
        $this->assertSame([], User::baselinePermissionsFor(null));
    }
}
