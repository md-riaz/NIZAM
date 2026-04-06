<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallTraceEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'call_session_id' => $this->call_session_id,
            'call_uuid' => $this->call_uuid,
            'node_id' => $this->node_id,
            'node_type' => $this->node_type,
            'action' => $this->action,
            'payload' => $this->payload,
            'occurred_at' => $this->occurred_at,
            'created_at' => $this->created_at,
        ];
    }
}
