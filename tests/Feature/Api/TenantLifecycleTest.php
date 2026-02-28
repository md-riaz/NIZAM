<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_tenant_can_be_created_with_status(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tenants', [
                'name' => 'Trial Tenant',
                'domain' => 'trial.example.com',
                'slug' => 'trial-tenant',
                'status' => 'trial',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => 'trial']);
        $this->assertDatabaseHas('tenants', ['domain' => 'trial.example.com', 'status' => 'trial']);
    }

    public function test_tenant_defaults_to_active_status(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tenants', [
                'name' => 'Default Status Tenant',
                'domain' => 'default.example.com',
                'slug' => 'default-tenant',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => 'active']);
    }

    public function test_tenant_status_can_be_updated(): void
    {
        $user = $this->adminUser();
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/tenants/{$tenant->id}", [
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'slug' => $tenant->slug,
                'status' => 'suspended',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'suspended']);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tenants', [
                'name' => 'Bad Status',
                'domain' => 'bad.example.com',
                'slug' => 'bad-tenant',
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_tenant_resource_includes_status_and_quotas(): void
    {
        $user = $this->adminUser();
        $tenant = Tenant::factory()->create([
            'status' => 'active',
            'max_concurrent_calls' => 10,
            'max_dids' => 20,
            'max_ring_groups' => 5,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tenants/{$tenant->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'active',
            'max_concurrent_calls' => 10,
            'max_dids' => 20,
            'max_ring_groups' => 5,
        ]);
    }

    public function test_tenant_is_operational_for_active_status(): void
    {
        $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->assertTrue($tenant->isOperational());
    }

    public function test_tenant_is_operational_for_trial_status(): void
    {
        $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_TRIAL]);
        $this->assertTrue($tenant->isOperational());
    }

    public function test_tenant_is_not_operational_for_suspended_status(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $this->assertFalse($tenant->isOperational());
    }

    public function test_tenant_is_not_operational_for_terminated_status(): void
    {
        $tenant = Tenant::factory()->terminated()->create();
        $this->assertFalse($tenant->isOperational());
    }
}
