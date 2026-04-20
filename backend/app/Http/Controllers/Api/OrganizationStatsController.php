<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * API controller for organization dashboard statistics.
 */
class OrganizationStatsController extends Controller
{
    /**
     * Return aggregate statistics for the given organization.
     */
    public function __invoke(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        return response()->json([
            'data' => [
                'extensions_count' => $organization->extensions()->count(),
                'active_extensions_count' => $organization->extensions()->where('is_active', true)->count(),
                'dids_count' => $organization->dids()->count(),
                'ring_groups_count' => $organization->ringGroups()->count(),
                'ivrs_count' => $organization->ivrs()->count(),
                'cdrs_total' => $organization->cdrs()->count(),
                'cdrs_today' => $organization->cdrs()->whereDate('start_stamp', Carbon::today())->count(),
                'recordings_count' => $organization->recordings()->count(),
                'recordings_total_size' => (int) $organization->recordings()->sum('file_size'),
                'device_profiles_count' => $organization->deviceProfiles()->count(),
                'webhooks_count' => $organization->webhooks()->count(),
                'call_routing_policies_count' => $organization->callRoutingPolicies()->count(),
                'flows_count' => $organization->flows()->count(),
                'quotas' => [
                    'max_extensions' => $organization->max_extensions,
                    'max_concurrent_calls' => $organization->max_concurrent_calls,
                    'max_dids' => $organization->max_dids,
                    'max_ring_groups' => $organization->max_ring_groups,
                ],
            ],
        ]);
    }
}
