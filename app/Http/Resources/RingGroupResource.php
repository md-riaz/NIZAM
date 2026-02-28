<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RingGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'strategy' => $this->strategy,
            'ring_timeout' => $this->ring_timeout,
            'members' => $this->members,
            'fallback_destination_type' => $this->fallback_destination_type,
            'fallback_destination_id' => $this->fallback_destination_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
