<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallDeliveryAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'call_session_id' => $this->call_session_id,
            'endpoint_binding_id' => $this->endpoint_binding_id,
            'attempt_type' => $this->attempt_type,
            'status' => $this->status,
            'freeswitch_leg_uuid' => $this->freeswitch_leg_uuid,
            'started_at' => $this->started_at,
            'answered_at' => $this->answered_at,
            'ended_at' => $this->ended_at,
            'failure_reason' => $this->failure_reason,
            'metadata' => $this->metadata,
            'endpoint' => $this->whenLoaded('endpointBinding', function (): array {
                return [
                    'id' => $this->endpointBinding?->id,
                    'type' => $this->endpointBinding?->type,
                    'extension_id' => $this->endpointBinding?->extension_id,
                    'agent_id' => $this->endpointBinding?->agent_id,
                    'device_uuid' => $this->endpointBinding?->device_uuid,
                    'platform' => $this->endpointBinding?->platform,
                    'is_push_capable' => $this->endpointBinding?->is_push_capable,
                    'forward_number' => $this->endpointBinding?->forward_number,
                    'forward_requires_confirm' => $this->endpointBinding?->forward_requires_confirm,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
