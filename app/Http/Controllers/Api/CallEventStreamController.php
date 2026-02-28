<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallEventLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-Sent Events (SSE) endpoint for real-time call event streaming.
 */
class CallEventStreamController extends Controller
{
    /**
     * Stream call events in real-time via SSE.
     *
     * Clients connect and receive new call events as they are persisted.
     * Supports Last-Event-ID for reconnection resumption.
     */
    public function stream(Request $request, Tenant $tenant): StreamedResponse
    {
        $this->authorize('viewAny', CallEventLog::class);

        $lastEventId = $request->header('Last-Event-ID');
        $callUuid = $request->query('call_uuid');

        return new StreamedResponse(function () use ($tenant, $lastEventId, $callUuid) {
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            $lastId = $lastEventId;
            $heartbeatInterval = 15; // seconds
            $lastHeartbeat = time();
            $maxDuration = 300; // 5 minutes max connection

            $startTime = time();

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

                $events = $query->limit(50)->get();

                foreach ($events as $event) {
                    $data = json_encode([
                        'id' => $event->id,
                        'call_uuid' => $event->call_uuid,
                        'event_type' => $event->event_type,
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
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
