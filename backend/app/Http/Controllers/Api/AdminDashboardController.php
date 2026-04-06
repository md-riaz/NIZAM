<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
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

        if ($user->role !== 'admin') {
            abort(403);
        }

        $tenants = Tenant::all();

        $statusCounts = $tenants->groupBy('status')->map->count();

        $perTenantStats = $tenants->map(function (Tenant $tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'status' => $tenant->status,
                'extensions_count' => $tenant->extensions()->count(),
                'active_extensions_count' => $tenant->extensions()->where('is_active', true)->count(),
                'dids_count' => $tenant->dids()->count(),
                'ring_groups_count' => $tenant->ringGroups()->count(),
                'recordings_total_size' => (int) $tenant->recordings()->sum('file_size'),
                'cdrs_today' => $tenant->cdrs()->whereDate('start_stamp', Carbon::today())->count(),
                'webhooks_count' => $tenant->webhooks()->count(),
            ];
        });

        return response()->json([
            'data' => [
                'total_tenants' => $tenants->count(),
                'tenants_by_status' => [
                    'trial' => $statusCounts->get(Tenant::STATUS_TRIAL, 0),
                    'active' => $statusCounts->get(Tenant::STATUS_ACTIVE, 0),
                    'suspended' => $statusCounts->get(Tenant::STATUS_SUSPENDED, 0),
                    'terminated' => $statusCounts->get(Tenant::STATUS_TERMINATED, 0),
                ],
                'total_extensions' => $perTenantStats->sum('extensions_count'),
                'total_active_extensions' => $perTenantStats->sum('active_extensions_count'),
                'total_dids' => $perTenantStats->sum('dids_count'),
                'total_recordings_size' => $perTenantStats->sum('recordings_total_size'),
                'tenants' => $perTenantStats->values(),
            ],
        ]);
    }
}
