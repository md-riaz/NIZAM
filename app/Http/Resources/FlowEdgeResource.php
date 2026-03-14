<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlowEdgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_node_id' => $this->source_node_id,
            'target_node_id' => $this->target_node_id,
            'condition' => $this->condition,
        ];
    }
}
