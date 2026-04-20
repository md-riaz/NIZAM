<?php

namespace Tests\Unit\Policies;

use App\Models\Flow;
use App\Models\CallRoutingPolicy;
use App\Models\Organization;
use App\Models\User;
use App\Policies\FlowPolicy;
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
        $policy = new FlowPolicy;

        $this->assertTrue($policy->before($admin, 'viewAny'));
        $this->assertTrue($policy->before($admin, 'create'));
        $this->assertTrue($policy->before($admin, 'update'));
        $this->assertTrue($policy->before($admin, 'delete'));
    }

    public function test_organization_user_can_view_own_call_routing_policies(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $crp = CallRoutingPolicy::factory()->create(['organization_id' => $organization->id]);

        $policy = new CallRoutingPolicyPolicy;

        // Without explicit permission, hasPermission returns true (default-open)
        $this->assertTrue($policy->view($user, $crp));
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

    public function test_organization_user_can_view_own_call_flows(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $flow = Flow::factory()->create(['organization_id' => $organization->id]);

        $policy = new FlowPolicy;

        $this->assertTrue($policy->view($user, $flow));
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
