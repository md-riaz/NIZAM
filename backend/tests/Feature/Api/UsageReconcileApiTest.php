<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Models\UsageRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageReconcileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_endpoint_returns_comparison(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        $today = Carbon::today();

        CallDetailRecord::factory()->create([
            'organization_id' => $organization->id,
            'billsec' => 120,
            'start_stamp' => $today->copy()->setTime(10, 0),
        ]);

        UsageRecord::factory()->create([
            'organization_id' => $organization->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 2.0,
            'recorded_date' => $today->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/usage/reconcile");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'cdr_total_seconds',
                'cdr_total_minutes',
                'metered_minutes',
                'difference_minutes',
                'matched',
            ],
        ]);
    }

    public function test_unauthenticated_cannot_access_reconcile(): void
    {
        $organization = Organization::factory()->create();

        $response = $this->getJson("/api/v1/organizations/{$organization->id}/usage/reconcile");

        $response->assertStatus(401);
    }
}
