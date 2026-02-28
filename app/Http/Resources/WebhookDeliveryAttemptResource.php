<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_id' => $this->webhook_id,
            'event_type' => $this->event_type,
            'payload' => $this->payload,
            'response_status' => $this->response_status,
            'attempt' => $this->attempt,
            'success' => $this->success,
            'error_message' => $this->error_message,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
        ];
    }
}
