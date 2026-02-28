<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\Tenant;
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
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
        $today = Carbon::today();

        CallDetailRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'billsec' => 120,
            'start_stamp' => $today->copy()->setTime(10, 0),
        ]);

        UsageRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
            'value' => 2.0,
            'recorded_date' => $today->toDateString(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tenants/{$tenant->id}/usage/reconcile");

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
        $tenant = Tenant::factory()->create();

        $response = $this->getJson("/api/tenants/{$tenant->id}/usage/reconcile");

        $response->assertStatus(401);
    }
}
