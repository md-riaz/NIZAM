<?php

namespace App\Services\Call;

use App\Models\CallSession;
use App\Models\CallTraceEvent;

class TraceWriter
{
    public function write(CallSession $callSession, string $action, array $payload = [], ?string $nodeId = null, ?string $nodeType = null): CallTraceEvent
    {
        return $callSession->traceEvents()->create([
            'call_uuid' => $callSession->call_uuid,
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'action' => $action,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
