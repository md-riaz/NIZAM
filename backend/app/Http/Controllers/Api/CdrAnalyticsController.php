<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Cdr\CdrAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CdrAnalyticsController extends Controller
{
    public function __construct(
        protected CdrAnalyticsService $analyticsService
    ) {}

    /**
     * Get overall CDR analytics summary for a tenant.
     *
     * GET /api/tenants/{tenant}/cdrs/analytics/summary
     */
    public function summary(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        $from = Carbon::parse($request->input('date_from', now()->subDays(30)->toDateString()));
        $to = Carbon::parse($request->input('date_to', now()->toDateString()));

        $data = $this->analyticsService->getSummary($tenant, $from, $to);

        return response()->json(['data' => $data]);
    }

    /**
     * Get call volume over time for a tenant.
     *
     * GET /api/tenants/{tenant}/cdrs/analytics/volume
     */
    public function volume(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        $from = Carbon::parse($request->input('date_from', now()->subDays(30)->toDateString()));
        $to = Carbon::parse($request->input('date_to', now()->toDateString()));
        $granularity = $request->input('granularity', 'daily');

        $data = $this->analyticsService->getVolume($tenant, $from, $to, $granularity);

        return response()->json(['data' => $data]);
    }

    /**
     * Get quality metrics trends over time.
     *
     * GET /api/tenants/{tenant}/cdrs/analytics/quality
     */
    public function quality(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        $from = Carbon::parse($request->input('date_from', now()->subDays(30)->toDateString()));
        $to = Carbon::parse($request->input('date_to', now()->toDateString()));
        $granularity = $request->input('granularity', 'daily');

        $data = $this->analyticsService->getQualityTrends($tenant, $from, $to, $granularity);

        return response()->json(['data' => $data]);
    }

    /**
     * Get top destinations by call count.
     *
     * GET /api/tenants/{tenant}/cdrs/analytics/destinations
     */
    public function destinations(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        $from = Carbon::parse($request->input('date_from', now()->subDays(30)->toDateString()));
        $to = Carbon::parse($request->input('date_to', now()->toDateString()));
        $limit = (int) $request->input('limit', 20);

        $data = $this->analyticsService->getTopDestinations($tenant, $from, $to, min($limit, 100));

        return response()->json(['data' => $data]);
    }
}
