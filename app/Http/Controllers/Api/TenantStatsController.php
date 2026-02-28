<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * API controller for tenant dashboard statistics.
 */
class TenantStatsController extends Controller
{
    /**
     * Return aggregate statistics for the given tenant.
     */
    public function __invoke(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        return response()->json([
            'data' => [
                'extensions_count' => $tenant->extensions()->count(),
                'active_extensions_count' => $tenant->extensions()->where('is_active', true)->count(),
                'dids_count' => $tenant->dids()->count(),
                'ring_groups_count' => $tenant->ringGroups()->count(),
                'ivrs_count' => $tenant->ivrs()->count(),
                'cdrs_total' => $tenant->cdrs()->count(),
                'cdrs_today' => $tenant->cdrs()->whereDate('start_stamp', Carbon::today())->count(),
                'recordings_count' => $tenant->recordings()->count(),
                'recordings_total_size' => (int) $tenant->recordings()->sum('file_size'),
                'device_profiles_count' => $tenant->deviceProfiles()->count(),
                'webhooks_count' => $tenant->webhooks()->count(),
                'call_routing_policies_count' => $tenant->callRoutingPolicies()->count(),
                'call_flows_count' => $tenant->callFlows()->count(),
            ],
        ]);
    }
}
