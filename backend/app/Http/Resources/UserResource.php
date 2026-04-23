<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'organization_id' => $this->organization_id,
            'role' => $this->role,
            'default_outbound_did_id' => $this->default_outbound_did_id,
            'direct_phone_numbers' => DidResource::collection($this->whenLoaded('directPhoneNumbers')),
            'effective_phone_numbers' => DidResource::collection($this->resource->effectivePhoneNumbers()),
            'email_verified_at' => $this->email_verified_at,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
