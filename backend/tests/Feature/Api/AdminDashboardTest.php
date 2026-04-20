<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        Organization::factory()->create(['status' => Organization::STATUS_ACTIVE]);
        Organization::factory()->create(['status' => Organization::STATUS_TRIAL]);
        Organization::factory()->suspended()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_organizations',
                'organizations_by_status' => ['trial', 'active', 'suspended', 'terminated'],
                'total_extensions',
                'total_active_extensions',
                'total_dids',
                'total_recordings_size',
                'organizations',
            ],
        ]);
        $response->assertJsonPath('data.total_organizations', 3);
        $response->assertJsonPath('data.organizations_by_status.active', 1);
        $response->assertJsonPath('data.organizations_by_status.trial', 1);
        $response->assertJsonPath('data.organizations_by_status.suspended', 1);
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(401);
    }

    public function test_dashboard_includes_per_organization_stats(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $organization = Organization::factory()->create(['status' => Organization::STATUS_ACTIVE]);
        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_extensions', 1);
        $response->assertJsonPath('data.total_active_extensions', 1);
    }
}
