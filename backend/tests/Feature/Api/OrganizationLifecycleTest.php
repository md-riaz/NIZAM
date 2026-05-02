<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);
    }

    public function test_organization_can_be_created_with_status(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'Trial Organization',
                'domain_prefix' => 'trial',
                'status' => 'trial',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => 'trial']);
        $this->assertDatabaseHas('organizations', ['domain' => 'trial', 'status' => 'trial']);
    }

    public function test_organization_defaults_to_active_status(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'Default Status Organization',
                'domain_prefix' => 'default',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => 'active']);
    }

    public function test_organization_status_can_be_updated(): void
    {
        $user = $this->adminUser();
        $organization = Organization::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}", [
                'name' => $organization->name,
                'domain_prefix' => $organization->domain,
                'status' => 'suspended',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'suspended']);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/organizations', [
                'name' => 'Bad Status',
                'domain_prefix' => 'bad',
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_organization_resource_includes_status_and_quotas(): void
    {
        $user = $this->adminUser();
        $organization = Organization::factory()->create([
            'status' => 'active',
            'max_concurrent_calls' => 10,
            'max_dids' => 20,
            'max_teams' => 5,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'active',
            'max_concurrent_calls' => 10,
            'max_dids' => 20,
            'max_teams' => 5,
        ]);
    }

    public function test_organization_is_operational_for_active_status(): void
    {
        $organization = Organization::factory()->create(['status' => Organization::STATUS_ACTIVE]);
        $this->assertTrue($organization->isOperational());
    }

    public function test_organization_is_operational_for_trial_status(): void
    {
        $organization = Organization::factory()->create(['status' => Organization::STATUS_TRIAL]);
        $this->assertTrue($organization->isOperational());
    }

    public function test_organization_is_not_operational_for_suspended_status(): void
    {
        $organization = Organization::factory()->suspended()->create();
        $this->assertFalse($organization->isOperational());
    }

    public function test_organization_is_not_operational_for_terminated_status(): void
    {
        $organization = Organization::factory()->terminated()->create();
        $this->assertFalse($organization->isOperational());
    }
}
