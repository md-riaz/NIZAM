<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\UsageMeteringService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for tenant usage metering.
 */
class UsageController extends Controller
{
    /**
     * Get usage summary for a tenant.
     */
    public function summary(Request $request, Tenant $tenant, UsageMeteringService $metering): JsonResponse
    {
        $this->authorize('view', $tenant);

        $from = $request->has('from')
            ? Carbon::parse($request->input('from'))
            : Carbon::today()->startOfMonth();

        $to = $request->has('to')
            ? Carbon::parse($request->input('to'))
            : Carbon::today();

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'usage' => $metering->getSummary($tenant, $from, $to),
            ],
        ]);
    }

    /**
     * Collect and record current usage snapshot.
     */
    public function collect(Tenant $tenant, UsageMeteringService $metering): JsonResponse
    {
        $this->authorize('update', $tenant);

        $records = $metering->collectSnapshot($tenant);

        return response()->json([
            'data' => [
                'recorded' => count($records),
                'date' => Carbon::today()->toDateString(),
            ],
        ], 201);
    }

    /**
     * Reconcile CDR billable minutes against metered call_minutes.
     */
    public function reconcile(Request $request, Tenant $tenant, UsageMeteringService $metering): JsonResponse
    {
        $this->authorize('view', $tenant);

        $from = $request->has('from')
            ? Carbon::parse($request->input('from'))
            : Carbon::today()->startOfMonth();

        $to = $request->has('to')
            ? Carbon::parse($request->input('to'))
            : Carbon::today();

        return response()->json([
            'data' => $metering->reconcileCallMinutes($tenant, $from, $to),
        ]);
    }
}
