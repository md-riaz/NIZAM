<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for querying audit logs (read-only).
 */
class AuditLogController extends Controller
{
    /**
     * List audit logs for a tenant.
     */
    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->input('auditable_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $logs = $query->paginate(50);

        return response()->json($logs);
    }

    /**
     * Show a single audit log entry.
     */
    public function show(Tenant $tenant, AuditLog $auditLog): JsonResponse
    {
        $this->authorize('view', $auditLog);

        if ($auditLog->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Audit log not found.'], 404);
        }

        return response()->json(['data' => $auditLog]);
    }
}
