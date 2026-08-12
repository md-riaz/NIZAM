<?php

namespace Tests\Unit\Policies;

use App\Models\CallRoutingPolicy;
use App\Models\Flow;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Policies\CallRoutingPolicyPolicy;
use App\Policies\FlowPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Spec3PoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_do_anything_on_call_routing_policy(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
        $policy = new CallRoutingPolicyPolicy;

        $this->assertTrue($policy->before($admin, 'viewAny'));
        $this->assertTrue($policy->before($admin, 'create'));
        $this->assertTrue($policy->before($admin, 'update'));
        $this->assertTrue($policy->before($admin, 'delete'));
    }

    public function test_superadmin_can_do_anything_on_call_flow(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
        $policy = new FlowPolicy;

        $this->assertTrue($policy->before($admin, 'viewAny'));
        $this->assertTrue($policy->before($admin, 'create'));
        $this->assertTrue($policy->before($admin, 'update'));
        $this->assertTrue($policy->before($admin, 'delete'));
    }

    public function test_organization_user_without_permission_cannot_view_own_call_routing_policies(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $crp = CallRoutingPolicy::factory()->create(['organization_id' => $organization->id]);

        $policy = new CallRoutingPolicyPolicy;

        // Deny-by-default: being in the right organization is not enough without
        // the call_routing_policies.view grant. This used to pass on the removed
        // default-open fallback.
        $this->assertFalse($policy->view($user, $crp));
    }

    /**
     * The positive counterpart: with the grant, an organization member does get
     * access. Asserting only the denial would leave the permission itself
     * untested — a policy that returned false unconditionally would still pass.
     */
    public function test_granted_organization_user_can_view_own_call_routing_policies(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $crp = CallRoutingPolicy::factory()->create(['organization_id' => $organization->id]);

        Permission::updateOrCreate(['slug' => 'call_routing_policies.view'], ['module' => 'core']);
        $user->grantPermissions(['call_routing_policies.view']);

        $this->assertTrue((new CallRoutingPolicyPolicy)->view($user->fresh(), $crp));
    }

    public function test_organization_user_cannot_view_other_organizations_policy(): void
    {
        $organization1 = Organization::factory()->create();
        $organization2 = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization1->id]);
        $crp = CallRoutingPolicy::factory()->create(['organization_id' => $organization2->id]);

        $policy = new CallRoutingPolicyPolicy;

        $this->assertFalse($policy->view($user, $crp));
    }

    public function test_organization_user_without_permission_cannot_view_own_call_flows(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $flow = Flow::factory()->create(['organization_id' => $organization->id]);

        $policy = new FlowPolicy;

        // Deny-by-default: same reasoning as the call-routing-policy case above —
        // organization match alone no longer grants view access.
        $this->assertFalse($policy->view($user, $flow));
    }

    public function test_granted_organization_user_can_view_own_call_flows(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $flow = Flow::factory()->create(['organization_id' => $organization->id]);

        Permission::updateOrCreate(['slug' => 'flows.view'], ['module' => 'core']);
        $user->grantPermissions(['flows.view']);

        $this->assertTrue((new FlowPolicy)->view($user->fresh(), $flow));
    }

    public function test_organization_user_cannot_view_other_organizations_flow(): void
    {
        $organization1 = Organization::factory()->create();
        $organization2 = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization1->id]);
        $flow = Flow::factory()->create(['organization_id' => $organization2->id]);

        $policy = new FlowPolicy;

        $this->assertFalse($policy->view($user, $flow));
    }
}
