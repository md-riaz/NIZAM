<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'extension_id' => $this->extension_id,
            'type' => $this->type,
            'device_uuid' => $this->device_uuid,
            'platform' => $this->platform,
            'app_version' => $this->app_version,
            'is_enabled' => $this->is_enabled,
            'is_push_capable' => $this->is_push_capable,
            'push_enabled' => data_get($this->metadata, 'push_enabled', $this->is_push_capable),
            'sip_background_mode_supported' => data_get($this->metadata, 'sip_background_mode_supported', $this->rings_immediately_when_online),
            'allow_late_join_after_push' => $this->allow_late_join_after_push,
            'has_push_token' => filled($this->push_token),
            'has_voip_push_token' => filled($this->voip_push_token),
            'last_seen_at' => $this->last_seen_at,
            'last_registered_at' => $this->last_registered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
