<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantStatsQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_include_quota_utilization(): void
    {
        $tenant = Tenant::factory()->create([
            'max_extensions' => 50,
            'max_concurrent_calls' => 20,
            'max_dids' => 10,
            'max_ring_groups' => 5,
        ]);
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}/stats");

        $response->assertStatus(200);
        $response->assertJsonPath('data.extensions_count', 1);
        $response->assertJsonStructure([
            'data' => [
                'extensions_count',
                'active_extensions_count',
                'dids_count',
                'ring_groups_count',
                'quotas' => [
                    'max_extensions',
                    'max_concurrent_calls',
                    'max_dids',
                    'max_ring_groups',
                ],
            ],
        ]);
        $response->assertJsonPath('data.quotas.max_extensions', 50);
        $response->assertJsonPath('data.quotas.max_concurrent_calls', 20);
        $response->assertJsonPath('data.quotas.max_dids', 10);
        $response->assertJsonPath('data.quotas.max_ring_groups', 5);
    }
}
