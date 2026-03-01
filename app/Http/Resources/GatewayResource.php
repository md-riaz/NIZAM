<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GatewayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'realm' => $this->realm,
            'transport' => $this->transport,
            'inbound_codecs' => $this->inbound_codecs ?? [],
            'outbound_codecs' => $this->outbound_codecs ?? [],
            'allow_transcoding' => $this->allow_transcoding,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
