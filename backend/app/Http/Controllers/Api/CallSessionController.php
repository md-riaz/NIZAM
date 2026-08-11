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
        $this->authorize('viewAny', CallSession::class);

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
        $this->authorize('view', $callSession);

        if ($callSession->organization_id !== $organization->id) {
            return response()->json(['message' => 'Call session not found.'], 404);
        }

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
        $this->authorize('view', $callSession);

        if ($callSession->organization_id !== $organization->id) {
            return response()->json(['message' => 'Call session not found.'], 404);
        }

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
