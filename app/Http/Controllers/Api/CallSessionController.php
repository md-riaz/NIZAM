<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CallSessionResource;
use App\Models\CallSession;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller for viewing call sessions and trace events.
 */
class CallSessionController extends Controller
{
    /**
     * List call sessions for a tenant.
     */
    public function index(Tenant $tenant)
    {
        // Assuming Gate::authorize('viewAny', CallSession::class) logic can be wired later
        // Currently keeping it simple and tenant scoped
        return CallSessionResource::collection(
            $tenant->callSessions()
                ->orderBy('created_at', 'desc')
                ->paginate(15)
        );
    }

    /**
     * Show a specific call session with its trace events.
     */
    public function show(Tenant $tenant, CallSession $callSession): JsonResponse|CallSessionResource
    {
        if ($callSession->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Call session not found.'], 404);
        }

        $callSession->load(['traceEvents' => function ($query) {
            $query->orderBy('occurred_at', 'asc')->orderBy('id', 'asc');
        }]);

        return new CallSessionResource($callSession);
    }
}
