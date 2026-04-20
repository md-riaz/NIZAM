<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'description' => $this->description,
            'active_version_id' => $this->active_version_id,
            'active_version' => new FlowVersionResource($this->whenLoaded('activeVersion')),
            'latest_version' => new FlowVersionResource($this->whenLoaded('latestVersion')),
            'versions' => FlowVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
