<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CallSessionResource;
use App\Models\CallSession;
use App\Models\Organization;
use App\Services\Call\CallTraceAnalyzer;
use Illuminate\Http\JsonResponse;

/**
 * API controller for viewing call sessions and trace events.
 */
class CallSessionController extends Controller
{
    /**
     * List call sessions for an organization.
     */
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', [CallSession::class, $organization]);

        return CallSessionResource::collection(
            $organization->callSessions()
                ->with([
                    'winningDeliveryAttempt.endpointBinding',
                ])
                ->orderBy('created_at', 'desc')
                ->paginate(15)
        );
    }

    /**
     * Show a specific call session with its trace events.
     */
    public function show(Organization $organization, CallSession $callSession): JsonResponse|CallSessionResource
    {
        // Order matters: answer 404 for anything outside this organization before
        // authorizing, otherwise a foreign session returns 403 while an unknown
        // one returns 404 — an existence oracle for other tenants' session IDs.
        if ($callSession->organization_id !== $organization->id) {
            return response()->json(['message' => 'Call session not found.'], 404);
        }

        $this->authorize('view', $callSession);

        $callSession->load([
            'traceEvents' => function ($query) {
                $query->orderBy('occurred_at', 'asc')->orderBy('id', 'asc');
            },
            'deliveryAttempts' => function ($query) {
                $query->with('endpointBinding')
                    ->orderBy('started_at', 'asc')
                    ->orderBy('id', 'asc');
            },
            'winningDeliveryAttempt.endpointBinding',
            'pushNotificationLogs' => function ($query) {
                $query->with('endpointBinding')
                    ->orderBy('sent_at', 'asc')
                    ->orderBy('id', 'asc');
            },
        ]);

        return new CallSessionResource($callSession);
    }

    /**
     * Return computed replay timeline and node metrics for a call session.
     */
    public function analyze(Organization $organization, CallSession $callSession, CallTraceAnalyzer $analyzer): JsonResponse
    {
        // Order matters: answer 404 for anything outside this organization before
        // authorizing, otherwise a foreign session returns 403 while an unknown
        // one returns 404 — an existence oracle for other tenants' session IDs.
        if ($callSession->organization_id !== $organization->id) {
            return response()->json(['message' => 'Call session not found.'], 404);
        }

        $this->authorize('view', $callSession);

        $callSession->load([
            'deliveryAttempts' => function ($query) {
                $query->with('endpointBinding')
                    ->orderBy('started_at', 'asc')
                    ->orderBy('id', 'asc');
            },
            'winningDeliveryAttempt.endpointBinding',
            'pushNotificationLogs' => function ($query) {
                $query->with('endpointBinding')
                    ->orderBy('sent_at', 'asc')
                    ->orderBy('id', 'asc');
            },
        ]);

        return response()->json([
            'data' => $analyzer->analyze($callSession),
        ]);
    }
}
