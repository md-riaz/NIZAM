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
            'primary_extension_id' => $this->whenLoaded('primaryExtension', fn () => $this->primaryExtension?->id),
            'extension_ids' => $this->whenLoaded('extensions', fn () => $this->extensions->pluck('id')->values()->all()),
            'role' => $this->role,
            'email_verified_at' => $this->email_verified_at,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
