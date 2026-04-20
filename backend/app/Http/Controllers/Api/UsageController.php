<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\UsageMeteringService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for organization usage metering.
 */
class UsageController extends Controller
{
    /**
     * Get usage summary for an organization.
     */
    public function summary(Request $request, Organization $organization, UsageMeteringService $metering): JsonResponse
    {
        $this->authorize('view', $organization);

        $from = $request->has('from')
            ? Carbon::parse($request->input('from'))
            : Carbon::today()->startOfMonth();

        $to = $request->has('to')
            ? Carbon::parse($request->input('to'))
            : Carbon::today();

        return response()->json([
            'data' => [
                'organization_id' => $organization->id,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'usage' => $metering->getSummary($organization, $from, $to),
            ],
        ]);
    }

    /**
     * Collect and record current usage snapshot.
     */
    public function collect(Organization $organization, UsageMeteringService $metering): JsonResponse
    {
        $this->authorize('update', $organization);

        $records = $metering->collectSnapshot($organization);

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
    public function reconcile(Request $request, Organization $organization, UsageMeteringService $metering): JsonResponse
    {
        $this->authorize('view', $organization);

        $from = $request->has('from')
            ? Carbon::parse($request->input('from'))
            : Carbon::today()->startOfMonth();

        $to = $request->has('to')
            ? Carbon::parse($request->input('to'))
            : Carbon::today();

        return response()->json([
            'data' => $metering->reconcileCallMinutes($organization, $from, $to),
        ]);
    }
}
