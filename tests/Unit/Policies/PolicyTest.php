<?php

namespace Tests\Unit\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\DeviceProfilePolicy;
use App\Policies\DidPolicy;
use App\Policies\ExtensionPolicy;
use App\Policies\IvrPolicy;
use App\Policies\RingGroupPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TimeConditionPolicy;
use App\Policies\WebhookPolicy;
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

    public function test_admin_bypasses_did_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new DidPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_user_can_view_did_in_own_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $did = $tenant->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => 'test-id',
        ]);

        $policy = new DidPolicy;
        $this->assertTrue($policy->view($user, $did));
    }

    public function test_user_cannot_view_did_in_other_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'user']);
        $did = $tenantB->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => 'test-id',
        ]);

        $policy = new DidPolicy;
        $this->assertFalse($policy->view($user, $did));
    }

    public function test_admin_bypasses_ring_group_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new RingGroupPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_user_can_create_ring_group_with_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);

        $policy = new RingGroupPolicy;
        $this->assertTrue($policy->create($user));
    }

    public function test_user_without_tenant_cannot_create_ring_group(): void
    {
        $user = User::factory()->create(['tenant_id' => null, 'role' => 'user']);

        $policy = new RingGroupPolicy;
        $this->assertFalse($policy->create($user));
    }

    public function test_admin_bypasses_ivr_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new IvrPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_admin_bypasses_time_condition_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new TimeConditionPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_admin_bypasses_webhook_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new WebhookPolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_admin_bypasses_device_profile_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $policy = new DeviceProfilePolicy;
        $this->assertTrue($policy->before($admin, 'view'));
    }

    public function test_user_can_view_webhook_in_own_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $webhook = $tenant->webhooks()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['call.started'],
            'secret' => 'test-secret',
        ]);

        $policy = new WebhookPolicy;
        $this->assertTrue($policy->view($user, $webhook));
    }

    public function test_user_cannot_update_webhook_in_other_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'user']);
        $webhook = $tenantB->webhooks()->create([
            'url' => 'https://example.com/webhook',
            'events' => ['call.started'],
            'secret' => 'test-secret',
        ]);

        $policy = new WebhookPolicy;
        $this->assertFalse($policy->update($user, $webhook));
    }
}
