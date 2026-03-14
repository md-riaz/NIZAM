<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'call_uuid' => $this->call_uuid,
            'did_id' => $this->did_id,
            'flow_id' => $this->flow_version?->flow_id,
            'flow_version_id' => $this->flow_version_id,
            'current_node_id' => $this->current_node_id,
            'state' => $this->state,
            'variables' => $this->variables,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'trace_events' => CallTraceEventResource::collection($this->whenLoaded('traceEvents')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
