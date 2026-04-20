<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for querying audit logs (read-only).
 */
class AuditLogController extends Controller
{
    /**
     * List audit logs for an organization.
     */
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::where('organization_id', $organization->id)
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
    public function show(Organization $organization, AuditLog $auditLog): JsonResponse
    {
        $this->authorize('view', $auditLog);

        if ($auditLog->organization_id !== $organization->id) {
            return response()->json(['message' => 'Audit log not found.'], 404);
        }

        return response()->json(['data' => $auditLog]);
    }
}
