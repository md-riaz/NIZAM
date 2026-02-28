<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallEventLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-Sent Events (SSE) endpoint for real-time call event streaming.
 */
class CallEventStreamController extends Controller
{
    protected int $maxConnectionsPerTenant = 50;

    /**
     * Stream call events in real-time via SSE.
     *
     * Clients connect and receive new call events as they are persisted.
     * Supports Last-Event-ID for reconnection resumption.
     * Supports event_types filter for specific event types.
     */
    public function stream(Request $request, Tenant $tenant): StreamedResponse
    {
        $this->authorize('viewAny', CallEventLog::class);

        // Connection limit enforcement
        $connectionKey = "sse_connections:{$tenant->id}";
        $currentConnections = (int) Cache::get($connectionKey, 0);

        if ($currentConnections >= $this->maxConnectionsPerTenant) {
            return new StreamedResponse(function () {
                echo "event: error\ndata: {\"message\":\"Connection limit reached.\"}\n\n";
            }, 429, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ]);
        }

        Cache::increment($connectionKey);

        $lastEventId = $request->header('Last-Event-ID');
        $callUuid = $request->query('call_uuid');
        $eventTypes = $request->query('event_types')
            ? explode(',', $request->query('event_types'))
            : null;

        return new StreamedResponse(function () use ($tenant, $lastEventId, $callUuid, $eventTypes, $connectionKey) {
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            $lastId = $lastEventId;
            $heartbeatInterval = 15; // seconds
            $lastHeartbeat = time();
            $maxDuration = 300; // 5 minutes max connection

            $startTime = time();

            try {
                while (true) {
                    // Check max duration
                    if ((time() - $startTime) >= $maxDuration) {
                        echo "event: timeout\ndata: {\"message\":\"Connection timeout, please reconnect.\"}\n\n";

                        break;
                    }

                    // Query for new events
                    $query = CallEventLog::where('tenant_id', $tenant->id)
                        ->orderBy('id', 'asc');

                    if ($lastId) {
                        $query->where('id', '>', $lastId);
                    }

                    if ($callUuid) {
                        $query->where('call_uuid', $callUuid);
                    }

                    if ($eventTypes) {
                        $query->whereIn('event_type', $eventTypes);
                    }

                    $events = $query->limit(50)->get();

                    foreach ($events as $event) {
                        $data = json_encode([
                            'id' => $event->id,
                            'call_uuid' => $event->call_uuid,
                            'event_type' => $event->event_type,
                            'schema_version' => $event->schema_version,
                            'payload' => $event->payload,
                            'occurred_at' => $event->occurred_at?->toISOString(),
                        ]);

                        echo "id: {$event->id}\n";
                        echo "event: {$event->event_type}\n";
                        echo "data: {$data}\n\n";

                        $lastId = $event->id;
                    }

                    // Send heartbeat to keep connection alive
                    if ((time() - $lastHeartbeat) >= $heartbeatInterval) {
                        echo ": heartbeat\n\n";
                        $lastHeartbeat = time();
                    }

                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    flush();

                    // Check if client disconnected
                    if (connection_aborted()) {
                        break;
                    }

                    // Sleep before next poll
                    usleep(500000); // 500ms
                }
            } finally {
                Cache::decrement($connectionKey);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
