<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BridgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'bridge_type' => $this->bridge_type,
            'gateway_id' => $this->gateway_id,
            'destination_template' => $this->destination_template,
            'codec_policy' => $this->codec_policy,
            'codec_list' => $this->codec_list ?? [],
            'transcode_policy' => $this->transcode_policy,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
