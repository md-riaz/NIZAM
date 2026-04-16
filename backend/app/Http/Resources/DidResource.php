<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'number' => $this->number,
            'description' => $this->description,
            'destination_type' => $this->normalizeDestinationType(),
            'destination_id' => $this->destination_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function normalizeDestinationType(): ?string
    {
        return match ($this->destination_type) {
            'ivr', 'ring_group', 'flow' => 'flow',
            default => $this->destination_type,
        };
    }
}
