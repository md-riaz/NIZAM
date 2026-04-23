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
            'organization_id' => $this->organization_id,
            'gateway_id' => $this->gateway_id,
            'number' => $this->number,
            'normalized_number' => $this->normalized_number,
            'description' => $this->description,
            'destination_type' => $this->destination_type,
            'destination_id' => $this->destination_id,
            'is_active' => $this->is_active,
            'gateway' => GatewayResource::make($this->whenLoaded('gateway')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
