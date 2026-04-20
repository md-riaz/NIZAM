<?php

namespace Tests\Unit\Policies;

use App\Models\Extension;
use App\Models\Permission;
use App\Models\Organization;
use App\Models\User;
use App\Policies\ExtensionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionPolicyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_no_permissions_assigned_defaults_to_allow(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'user']);
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $policy = new ExtensionPolicy;

        // No permissions assigned → default-open
        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $extension));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $extension));
        $this->assertTrue($policy->delete($user, $extension));
    }

    public function test_user_with_view_only_permission_cannot_create(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'user']);
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $policy = new ExtensionPolicy;

        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);
        Permission::create(['slug' => 'extensions.create', 'module' => 'core']);

        // Grant only view
        $user->grantPermissions(['extensions.view']);

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $extension));
        $this->assertFalse($policy->create($user)); // Missing create permission
    }

    public function test_user_cannot_access_other_organization_even_with_permission(): void
    {
        $organization1 = Organization::factory()->create();
        $organization2 = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization1->id, 'role' => 'user']);
        $extension = Extension::factory()->create(['organization_id' => $organization2->id]);
        $policy = new ExtensionPolicy;

        // Even with no specific permissions (default-open), organization boundary is enforced
        $this->assertFalse($policy->view($user, $extension));
        $this->assertFalse($policy->update($user, $extension));
        $this->assertFalse($policy->delete($user, $extension));
    }

    public function test_admin_bypasses_all_permission_checks(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $policy = new ExtensionPolicy;

        // Admin bypasses via before()
        $this->assertTrue($policy->before($admin, 'viewAny'));
    }
}
