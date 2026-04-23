<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'vendor' => $this->vendor,
            'mac_address' => $this->mac_address,
            'template' => $this->template,
            'extension_id' => $this->extension_id,
            'owned_extension_ids' => $this->whenLoaded('ownedExtensions', fn () => $this->ownedExtensions->pluck('id')->values()->all()),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
