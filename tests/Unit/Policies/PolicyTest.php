<?php

namespace Tests\Unit\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\ExtensionPolicy;
use App\Policies\TenantPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bypasses_tenant_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $policy = new TenantPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_non_admin_cannot_create_tenant(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $policy = new TenantPolicy;
        $this->assertNull($policy->before($user, 'create'));
        $this->assertFalse($policy->create($user));
    }

    public function test_user_can_view_own_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);

        $policy = new TenantPolicy;
        $this->assertTrue($policy->view($user, $tenant));
    }

    public function test_user_cannot_view_other_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'user']);

        $policy = new TenantPolicy;
        $this->assertFalse($policy->view($user, $tenantB));
    }

    public function test_admin_bypasses_extension_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $policy = new ExtensionPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_user_can_view_extension_in_own_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'pass',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
        ]);

        $policy = new ExtensionPolicy;
        $this->assertTrue($policy->view($user, $extension));
    }

    public function test_user_cannot_view_extension_in_other_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'user']);
        $extension = $tenantB->extensions()->create([
            'extension' => '1001',
            'password' => 'pass',
            'directory_first_name' => 'Jane',
            'directory_last_name' => 'Doe',
        ]);

        $policy = new ExtensionPolicy;
        $this->assertFalse($policy->view($user, $extension));
    }
}
