<?php

namespace Tests\Unit\Policies;

use App\Models\Extension;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Policies\ExtensionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionPolicyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_no_permissions_assigned_is_denied(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'agent']);
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $policy = new ExtensionPolicy;

        // No permissions assigned → denied. Policies are deny-by-default, so an
        // agent nobody has configured cannot read or mutate extensions.
        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->view($user, $extension));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $extension));
        $this->assertFalse($policy->delete($user, $extension));
    }

    public function test_user_with_view_only_permission_cannot_create(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'agent']);
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
        $user = User::factory()->create(['organization_id' => $organization1->id, 'role' => 'agent']);
        $extension = Extension::factory()->create(['organization_id' => $organization2->id]);
        $policy = new ExtensionPolicy;

        // Even with no specific permissions (default-open), organization boundary is enforced
        $this->assertFalse($policy->view($user, $extension));
        $this->assertFalse($policy->update($user, $extension));
        $this->assertFalse($policy->delete($user, $extension));
    }

    public function test_superadmin_bypasses_all_permission_checks(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $policy = new ExtensionPolicy;

        // Superadmin bypasses via before()
        $this->assertTrue($policy->before($admin, 'viewAny'));
    }
}
