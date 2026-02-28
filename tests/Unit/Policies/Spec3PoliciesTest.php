<?php

namespace Tests\Unit\Policies;

use App\Models\CallFlow;
use App\Models\CallRoutingPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CallFlowPolicy;
use App\Policies\CallRoutingPolicyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Spec3PoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_do_anything_on_call_routing_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new CallRoutingPolicyPolicy;

        $this->assertTrue($policy->before($admin, 'viewAny'));
        $this->assertTrue($policy->before($admin, 'create'));
        $this->assertTrue($policy->before($admin, 'update'));
        $this->assertTrue($policy->before($admin, 'delete'));
    }

    public function test_admin_can_do_anything_on_call_flow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new CallFlowPolicy;

        $this->assertTrue($policy->before($admin, 'viewAny'));
        $this->assertTrue($policy->before($admin, 'create'));
        $this->assertTrue($policy->before($admin, 'update'));
        $this->assertTrue($policy->before($admin, 'delete'));
    }

    public function test_tenant_user_can_view_own_call_routing_policies(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $crp = CallRoutingPolicy::factory()->create(['tenant_id' => $tenant->id]);

        $policy = new CallRoutingPolicyPolicy;

        // Without explicit permission, hasPermission returns true (default-open)
        $this->assertTrue($policy->view($user, $crp));
    }

    public function test_tenant_user_cannot_view_other_tenants_policy(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant1->id]);
        $crp = CallRoutingPolicy::factory()->create(['tenant_id' => $tenant2->id]);

        $policy = new CallRoutingPolicyPolicy;

        $this->assertFalse($policy->view($user, $crp));
    }

    public function test_tenant_user_can_view_own_call_flows(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $flow = CallFlow::factory()->create(['tenant_id' => $tenant->id]);

        $policy = new CallFlowPolicy;

        $this->assertTrue($policy->view($user, $flow));
    }

    public function test_tenant_user_cannot_view_other_tenants_flow(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant1->id]);
        $flow = CallFlow::factory()->create(['tenant_id' => $tenant2->id]);

        $policy = new CallFlowPolicy;

        $this->assertFalse($policy->view($user, $flow));
    }
}
