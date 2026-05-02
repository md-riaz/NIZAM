<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * API controller for admin observability dashboard.
 */
class AdminDashboardController extends Controller
{
    /**
     * Return system-wide statistics for admin observability.
     */
    public function __invoke(): JsonResponse
    {
        $user = request()->user();

        if (! $user->isSuperadmin()) {
            abort(403);
        }

        $organizations = Organization::all();

        $statusCounts = $organizations->groupBy('status')->map->count();

        $perOrganizationStats = $organizations->map(function (Organization $organization) {
            return [
                'id' => $organization->id,
                'name' => $organization->name,
                'domain' => $organization->domain,
                'status' => $organization->status,
                'extensions_count' => $organization->extensions()->count(),
                'active_extensions_count' => $organization->extensions()->where('is_active', true)->count(),
                'dids_count' => $organization->dids()->count(),
                'teams_count' => $organization->teams()->count(),
                'recordings_total_size' => (int) $organization->recordings()->sum('file_size'),
                'cdrs_today' => $organization->cdrs()->whereDate('start_stamp', Carbon::today())->count(),
                'webhooks_count' => $organization->webhooks()->count(),
            ];
        });

        return response()->json([
            'data' => [
                'total_organizations' => $organizations->count(),
                'organizations_by_status' => [
                    'trial' => $statusCounts->get(Organization::STATUS_TRIAL, 0),
                    'active' => $statusCounts->get(Organization::STATUS_ACTIVE, 0),
                    'suspended' => $statusCounts->get(Organization::STATUS_SUSPENDED, 0),
                    'terminated' => $statusCounts->get(Organization::STATUS_TERMINATED, 0),
                ],
                'total_extensions' => $perOrganizationStats->sum('extensions_count'),
                'total_active_extensions' => $perOrganizationStats->sum('active_extensions_count'),
                'total_dids' => $perOrganizationStats->sum('dids_count'),
                'total_recordings_size' => $perOrganizationStats->sum('recordings_total_size'),
                'organizations' => $perOrganizationStats->values(),
            ],
        ]);
    }
}
