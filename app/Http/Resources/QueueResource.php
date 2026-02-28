<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'strategy' => $this->strategy,
            'max_wait_time' => $this->max_wait_time,
            'overflow_action' => $this->overflow_action,
            'overflow_destination' => $this->overflow_destination,
            'music_on_hold' => $this->music_on_hold,
            'service_level_threshold' => $this->service_level_threshold,
            'is_active' => $this->is_active,
            'members_count' => $this->whenCounted('members'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
