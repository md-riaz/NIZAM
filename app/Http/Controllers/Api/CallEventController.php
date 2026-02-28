<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallEventLog;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for querying call event logs for replay and tracing.
 */
class CallEventController extends Controller
{
    /**
     * List call events for a tenant, optionally filtered by call UUID.
     */
    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAny', CallEventLog::class);
        $query = CallEventLog::where('tenant_id', $tenant->id)
            ->orderBy('occurred_at', 'asc');

        if ($request->filled('call_uuid')) {
            $query->where('call_uuid', $request->input('call_uuid'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->input('to'));
        }

        $events = $query->paginate(50);

        return response()->json($events);
    }

    /**
     * Get the full trace for a specific call UUID.
     */
    public function trace(Tenant $tenant, string $callUuid): JsonResponse
    {
        $this->authorize('viewAny', CallEventLog::class);
        $events = CallEventLog::where('tenant_id', $tenant->id)
            ->where('call_uuid', $callUuid)
            ->orderBy('occurred_at', 'asc')
            ->get();

        if ($events->isEmpty()) {
            return response()->json(['message' => 'No events found for this call UUID.'], 404);
        }

        return response()->json([
            'call_uuid' => $callUuid,
            'event_count' => $events->count(),
            'events' => $events,
        ]);
    }

    /**
     * Replay a specific event by its UUID.
     */
    public function replay(Tenant $tenant, string $eventId): JsonResponse
    {
        $this->authorize('viewAny', CallEventLog::class);

        $event = CallEventLog::where('tenant_id', $tenant->id)
            ->where('id', $eventId)
            ->first();

        if (! $event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        return response()->json([
            'id' => $event->id,
            'call_uuid' => $event->call_uuid,
            'event_type' => $event->event_type,
            'schema_version' => $event->schema_version,
            'payload' => $event->payload,
            'occurred_at' => $event->occurred_at?->toISOString(),
        ]);
    }
}
