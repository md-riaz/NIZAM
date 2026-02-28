<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        Tenant::factory()->create(['status' => Tenant::STATUS_TRIAL]);
        Tenant::factory()->suspended()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_tenants',
                'tenants_by_status' => ['trial', 'active', 'suspended', 'terminated'],
                'total_extensions',
                'total_active_extensions',
                'total_dids',
                'total_recordings_size',
                'tenants',
            ],
        ]);
        $response->assertJsonPath('data.total_tenants', 3);
        $response->assertJsonPath('data.tenants_by_status.active', 1);
        $response->assertJsonPath('data.tenants_by_status.trial', 1);
        $response->assertJsonPath('data.tenants_by_status.suspended', 1);
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['role' => 'user', 'tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(401);
    }

    public function test_dashboard_includes_per_tenant_stats(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_extensions', 1);
        $response->assertJsonPath('data.total_active_extensions', 1);
    }
}
