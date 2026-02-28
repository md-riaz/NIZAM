<?php

namespace Tests\Unit\Models;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_has_slug_and_description(): void
    {
        $permission = Permission::create([
            'slug' => 'extensions.view',
            'description' => 'View extensions',
            'module' => 'core',
        ]);

        $this->assertEquals('extensions.view', $permission->slug);
        $this->assertEquals('View extensions', $permission->description);
        $this->assertEquals('core', $permission->module);
    }

    public function test_permission_slug_is_unique(): void
    {
        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);
    }

    public function test_user_has_permission_relationship(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test',
            'domain' => 'test.com',
            'slug' => 'test',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $perm = Permission::create(['slug' => 'extensions.view', 'module' => 'core']);

        $user->permissions()->attach($perm->id);

        $this->assertCount(1, $user->permissions);
        $this->assertEquals('extensions.view', $user->permissions->first()->slug);
    }

    public function test_admin_always_has_permission(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test',
            'domain' => 'test.com',
            'slug' => 'test',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        // Admin has permission even without explicit assignment
        $this->assertTrue($admin->hasPermission('extensions.view'));
        $this->assertTrue($admin->hasPermission('anything.at.all'));
    }

    public function test_user_without_permission_returns_false(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test',
            'domain' => 'test.com',
            'slug' => 'test',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);

        $this->assertFalse($user->hasPermission('extensions.view'));
    }

    public function test_user_with_granted_permission_returns_true(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test',
            'domain' => 'test.com',
            'slug' => 'test',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);
        Permission::create(['slug' => 'extensions.create', 'module' => 'core']);

        $user->grantPermissions(['extensions.view']);

        $this->assertTrue($user->hasPermission('extensions.view'));
        $this->assertFalse($user->hasPermission('extensions.create'));
    }

    public function test_revoke_permissions(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test',
            'domain' => 'test.com',
            'slug' => 'test',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);
        Permission::create(['slug' => 'extensions.create', 'module' => 'core']);

        $user->grantPermissions(['extensions.view', 'extensions.create']);
        $this->assertTrue($user->hasPermission('extensions.view'));
        $this->assertTrue($user->hasPermission('extensions.create'));

        $user->revokePermissions(['extensions.create']);
        $this->assertTrue($user->hasPermission('extensions.view'));
        $this->assertFalse($user->hasPermission('extensions.create'));
    }

    public function test_grant_permissions_is_idempotent(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test',
            'domain' => 'test.com',
            'slug' => 'test',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        Permission::create(['slug' => 'extensions.view', 'module' => 'core']);

        $user->grantPermissions(['extensions.view']);
        $user->grantPermissions(['extensions.view']);

        $this->assertCount(1, $user->permissions);
    }
}
