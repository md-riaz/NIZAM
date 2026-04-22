<?php

namespace Tests\Unit\Policies;

use App\Models\Organization;
use App\Models\Extension;
use App\Models\User;
use App\Policies\DeviceProfilePolicy;
use App\Policies\DidPolicy;
use App\Policies\ExtensionPolicy;
use App\Policies\IvrPolicy;
use App\Policies\RingGroupPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\TimeConditionPolicy;
use App\Policies\WebhookPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_bypasses_organization_policy(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $policy = new OrganizationPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_non_admin_cannot_create_organization(): void
    {
        $user = User::factory()->create(['role' => 'agent']);

        $policy = new OrganizationPolicy;
        $this->assertNull($policy->before($user, 'create'));
        $this->assertFalse($policy->create($user));
    }

    public function test_user_can_view_own_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'agent']);

        $policy = new OrganizationPolicy;
        $this->assertTrue($policy->view($user, $organization));
    }

    public function test_user_cannot_view_other_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organizationA->id, 'role' => 'agent']);

        $policy = new OrganizationPolicy;
        $this->assertFalse($policy->view($user, $organizationB));
    }

    public function test_admin_bypasses_extension_policy(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $policy = new ExtensionPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_user_can_view_extension_in_own_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'agent']);
        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'pass',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $policy = new ExtensionPolicy;
        $this->assertTrue($policy->view($user, $extension));
    }

    public function test_user_cannot_view_extension_in_other_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organizationA->id, 'role' => 'agent']);
        $extension = $organizationB->extensions()->create([
            'extension' => '1001',
            'password' => 'pass',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $policy = new ExtensionPolicy;
        $this->assertFalse($policy->view($user, $extension));
    }

    public function test_admin_bypasses_did_policy(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
        $policy = new DidPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_user_can_view_did_in_own_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'agent']);
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $did = $organization->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
        ]);

        $policy = new DidPolicy;
        $this->assertTrue($policy->view($user, $did));
    }

    public function test_user_cannot_view_did_in_other_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organizationA->id, 'role' => 'agent']);
        $extension = Extension::factory()->create(['organization_id' => $organizationB->id]);
        $did = $organizationB->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
        ]);

        $policy = new DidPolicy;
        $this->assertFalse($policy->view($user, $did));
    }

    public function test_admin_bypasses_ring_group_policy(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
        $policy = new RingGroupPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_user_can_create_ring_group_with_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'agent']);

        $policy = new RingGroupPolicy;
        $this->assertTrue($policy->create($user));
    }

    public function test_user_without_organization_cannot_create_ring_group(): void
    {
        $user = User::factory()->create(['organization_id' => null, 'role' => 'agent']);

        $policy = new RingGroupPolicy;
        $this->assertFalse($policy->create($user));
    }

    public function test_admin_bypasses_ivr_policy(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
        $policy = new IvrPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_admin_bypasses_time_condition_policy(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
        $policy = new TimeConditionPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_admin_bypasses_webhook_policy(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
        $policy = new WebhookPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_admin_bypasses_device_profile_policy(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
        $policy = new DeviceProfilePolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_user_can_view_webhook_in_own_organization(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'agent']);
        $webhook = $organization->webhooks()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['call.created'],
            'secret' => 'test-secret',
        ]);

        $policy = new WebhookPolicy;
        $this->assertTrue($policy->view($user, $webhook));
    }

    public function test_user_cannot_update_webhook_in_other_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organizationA->id, 'role' => 'agent']);
        $webhook = $organizationB->webhooks()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['call.created'],
            'secret' => 'test-secret',
        ]);

        $policy = new WebhookPolicy;
        $this->assertFalse($policy->update($user, $webhook));
    }
}
