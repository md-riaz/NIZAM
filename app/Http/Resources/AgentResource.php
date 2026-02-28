<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'extension_id' => $this->extension_id,
            'name' => $this->name,
            'role' => $this->role,
            'state' => $this->state,
            'pause_reason' => $this->pause_reason,
            'state_changed_at' => $this->state_changed_at,
            'is_active' => $this->is_active,
            'extension' => $this->whenLoaded('extension', fn () => [
                'id' => $this->extension->id,
                'extension' => $this->extension->extension,
                'name' => trim($this->extension->directory_first_name.' '.$this->extension->directory_last_name),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
