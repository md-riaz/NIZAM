<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\ValidatesReportRange;
use App\Models\Organization;
use App\Services\Cdr\CdrAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CdrAnalyticsController extends Controller
{
    use ValidatesReportRange;

    public function __construct(
        protected CdrAnalyticsService $analyticsService
    ) {}

    /**
     * Get overall CDR analytics summary for an organization.
     *
     * GET /api/organizations/{organization}/cdrs/analytics/summary
     */
    public function summary(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        [$from, $to] = $this->reportRange($request);

        $data = $this->analyticsService->getSummary($organization, $from, $to);

        return response()->json(['data' => $data]);
    }

    /**
     * Get call volume over time for an organization.
     *
     * GET /api/organizations/{organization}/cdrs/analytics/volume
     */
    public function volume(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        [$from, $to] = $this->reportRange($request);
        $granularity = $request->validate([
            'granularity' => ['nullable', 'in:daily,hourly'],
        ])['granularity'] ?? 'daily';

        $data = $this->analyticsService->getVolume($organization, $from, $to, $granularity);

        return response()->json(['data' => $data]);
    }

    /**
     * Get quality metrics trends over time.
     *
     * GET /api/organizations/{organization}/cdrs/analytics/quality
     */
    public function quality(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        [$from, $to] = $this->reportRange($request);
        $granularity = $request->validate([
            'granularity' => ['nullable', 'in:daily,hourly'],
        ])['granularity'] ?? 'daily';

        $data = $this->analyticsService->getQualityTrends($organization, $from, $to, $granularity);

        return response()->json(['data' => $data]);
    }

    /**
     * Get top destinations by call count.
     *
     * GET /api/organizations/{organization}/cdrs/analytics/destinations
     */
    public function destinations(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\CallDetailRecord::class);

        [$from, $to] = $this->reportRange($request);
        $limit = (int) ($request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])['limit'] ?? 20);

        $data = $this->analyticsService->getTopDestinations($organization, $from, $to, $limit);

        return response()->json(['data' => $data]);
    }
}
