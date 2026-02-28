<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageMeteringTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(Tenant $tenant): User
    {
        return User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
    }

    public function test_can_get_usage_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->adminUser($tenant);

        UsageRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 120.5,
            'recorded_date' => now()->toDateString(),
        ]);

        UsageRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 60.25,
            'recorded_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tenants/{$tenant->id}/usage/summary");

        $response->assertStatus(200);
        $response->assertJsonPath('data.tenant_id', $tenant->id);
        $response->assertJsonStructure([
            'data' => [
                'tenant_id',
                'from',
                'to',
                'usage',
            ],
        ]);
    }

    public function test_can_collect_usage_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->adminUser($tenant);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/tenants/{$tenant->id}/usage/collect");

        $response->assertStatus(201);
        $response->assertJsonPath('data.recorded', 3);

        $this->assertDatabaseHas('usage_records', [
            'tenant_id' => $tenant->id,
            'metric' => UsageRecord::METRIC_ACTIVE_EXTENSIONS,
        ]);
    }

    public function test_usage_summary_filters_by_date_range(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->adminUser($tenant);

        UsageRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 100,
            'recorded_date' => '2026-01-15',
        ]);

        UsageRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 200,
            'recorded_date' => '2026-02-15',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tenants/{$tenant->id}/usage/summary?from=2026-02-01&to=2026-02-28");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_cannot_access_usage(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->getJson("/api/tenants/{$tenant->id}/usage/summary");

        $response->assertStatus(401);
    }
}
