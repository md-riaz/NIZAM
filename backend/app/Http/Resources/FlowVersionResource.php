<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlowVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'status' => $this->status,
            'is_published' => $this->is_published,
            'definition_checksum' => $this->definition_checksum,
            'nodes' => FlowNodeResource::collection($this->whenLoaded('nodes')),
            'edges' => FlowEdgeResource::collection($this->whenLoaded('edges')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
