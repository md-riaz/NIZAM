<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\ValidatesReportRange;
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
    use ValidatesReportRange;

    /**
     * Get usage summary for an organization.
     */
    public function summary(Request $request, Organization $organization, UsageMeteringService $metering): JsonResponse
    {
        $this->authorize('view', $organization);

        [$from, $to] = $this->usageRange($request);

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

        [$from, $to] = $this->usageRange($request);

        return response()->json([
            'data' => $metering->reconcileCallMinutes($organization, $from, $to),
        ]);
    }

    /**
     * The validated usage range, defaulting to month-to-date.
     *
     * Usage reads `from`/`to` rather than `date_from`/`date_to`, and defaults to
     * the current month rather than the last 30 days, so it cannot simply reuse
     * the shared report range — only its ordering, which must not trade an
     * explicit bound for the default generated opposite it.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function usageRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return $this->orderedRange(
            $this->suppliedBound($validated, 'from'),
            $this->suppliedBound($validated, 'to'),
            Carbon::today()->startOfMonth(),
            Carbon::today(),
        );
    }
}
