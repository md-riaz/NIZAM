<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'conditions' => $this->conditions,
            'match_destination_type' => $this->match_destination_type,
            'match_destination_id' => $this->match_destination_id,
            'no_match_destination_type' => $this->no_match_destination_type,
            'no_match_destination_id' => $this->no_match_destination_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
