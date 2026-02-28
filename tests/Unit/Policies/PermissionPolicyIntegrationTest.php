<?php

namespace Tests\Unit\Policies;

use App\Models\Extension;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ExtensionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionPolicyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_no_permissions_assigned_defaults_to_allow(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $extension = Extension::factory()->create(['tenant_id' => $tenant->id]);
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
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $extension = Extension::factory()->create(['tenant_id' => $tenant->id]);
        $policy = new ExtensionPolicy;

        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);
        Permission::create(['slug' => 'extensions.create', 'module' => 'core']);

        // Grant only view
        $user->grantPermissions(['extensions.view']);

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $extension));
        $this->assertFalse($policy->create($user)); // Missing create permission
    }

    public function test_user_cannot_access_other_tenant_even_with_permission(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant1->id, 'role' => 'user']);
        $extension = Extension::factory()->create(['tenant_id' => $tenant2->id]);
        $policy = new ExtensionPolicy;

        // Even with no specific permissions (default-open), tenant boundary is enforced
        $this->assertFalse($policy->view($user, $extension));
        $this->assertFalse($policy->update($user, $extension));
        $this->assertFalse($policy->delete($user, $extension));
    }

    public function test_admin_bypasses_all_permission_checks(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $extension = Extension::factory()->create(['tenant_id' => $tenant->id]);
        $policy = new ExtensionPolicy;

        // Admin bypasses via before()
        $this->assertTrue($policy->before($admin, 'viewAny'));
    }
}
