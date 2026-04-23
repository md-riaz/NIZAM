<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtensionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'device_profile_id' => $this->device_profile_id,
            'default_outbound_did_id' => $this->default_outbound_did_id,
            'default_outbound_gateway_id' => $this->default_outbound_gateway_id,
            'allowed_outbound_did_ids' => $this->whenLoaded('allowedOutboundDids', fn () => $this->allowedOutboundDids->pluck('id')->values()->all(), $this->allowedOutboundDids()->pluck('dids.id')->all()),
            'allowed_outbound_gateway_ids' => $this->whenLoaded('allowedOutboundGateways', fn () => $this->allowedOutboundGateways->pluck('id')->values()->all(), $this->allowedOutboundGateways()->pluck('gateways.id')->all()),
            'owner_type' => $this->owner_type,
            'owner_label' => $this->owner_label,
            'extension' => $this->extension,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'effective_caller_id_name' => $this->effective_caller_id_name,
            'voicemail_enabled' => $this->voicemail_enabled,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
