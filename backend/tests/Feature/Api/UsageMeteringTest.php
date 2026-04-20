<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageMeteringTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(Organization $organization): User
    {
        return User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
    }

    public function test_can_get_usage_summary(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->adminUser($organization);

        UsageRecord::factory()->create([
            'organization_id' => $organization->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 120.5,
            'recorded_date' => now()->toDateString(),
        ]);

        UsageRecord::factory()->create([
            'organization_id' => $organization->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 60.25,
            'recorded_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/usage/summary");

        $response->assertStatus(200);
        $response->assertJsonPath('data.organization_id', $organization->id);
        $response->assertJsonStructure([
            'data' => [
                'organization_id',
                'from',
                'to',
                'usage',
            ],
        ]);
    }

    public function test_can_collect_usage_snapshot(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['role' => 'superadmin', 'organization_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/usage/collect");

        $response->assertStatus(201);
        $response->assertJsonPath('data.recorded', 3);

        $this->assertDatabaseHas('usage_records', [
            'organization_id' => $organization->id,
            'metric' => UsageRecord::METRIC_ACTIVE_EXTENSIONS,
        ]);
    }

    public function test_usage_summary_filters_by_date_range(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->adminUser($organization);

        UsageRecord::factory()->create([
            'organization_id' => $organization->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 100,
            'recorded_date' => '2026-01-15',
        ]);

        UsageRecord::factory()->create([
            'organization_id' => $organization->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 200,
            'recorded_date' => '2026-02-15',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/usage/summary?from=2026-02-01&to=2026-02-28");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_cannot_access_usage(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->getJson("/api/v1/organizations/{$organization->id}/usage/summary");

        $response->assertStatus(401);
    }
}
