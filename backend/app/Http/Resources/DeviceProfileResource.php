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
            'default_outbound_did_id' => $this->default_outbound_did_id,
            'phone_numbers' => DidResource::collection($this->whenLoaded('phoneNumbers')),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
