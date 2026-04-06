<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PushNotificationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'call_session_id' => $this->call_session_id,
            'endpoint_binding_id' => $this->endpoint_binding_id,
            'push_type' => $this->push_type,
            'provider_message_id' => $this->provider_message_id,
            'status' => $this->status,
            'sent_at' => $this->sent_at,
            'response_payload' => $this->response_payload,
            'endpoint' => $this->whenLoaded('endpointBinding', function (): array {
                return [
                    'id' => $this->endpointBinding?->id,
                    'type' => $this->endpointBinding?->type,
                    'extension_id' => $this->endpointBinding?->extension_id,
                    'agent_id' => $this->endpointBinding?->agent_id,
                    'device_uuid' => $this->endpointBinding?->device_uuid,
                    'platform' => $this->endpointBinding?->platform,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
