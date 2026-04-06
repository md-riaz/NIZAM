<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IvrResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'greet_long' => $this->greet_long,
            'greet_short' => $this->greet_short,
            'timeout' => $this->timeout,
            'max_failures' => $this->max_failures,
            'options' => $this->options,
            'timeout_destination_type' => $this->timeout_destination_type,
            'timeout_destination_id' => $this->timeout_destination_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
