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
            'vendor' => $this->vendor,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'realm' => $this->realm,
            'transport' => $this->transport,
            'register' => $this->register,
            'proxy' => $this->proxy,
            'register_proxy' => $this->register_proxy,
            'from_domain' => $this->from_domain,
            'extension' => $this->extension,
            'inbound_codecs' => $this->inbound_codecs ?? [],
            'outbound_codecs' => $this->outbound_codecs ?? [],
            'preferred_codecs' => $this->preferred_codecs ?? [],
            'dtmf_mode' => $this->dtmf_mode,
            'srtp_mode' => $this->srtp_mode,
            'allow_transcoding' => $this->allow_transcoding,
            'expire_seconds' => $this->expire_seconds,
            'retry_seconds' => $this->retry_seconds,
            'caller_id_in_from' => $this->caller_id_in_from,
            'profile' => $this->profile,
            'is_active' => $this->is_active,
            'tenant' => TenantResource::make($this->whenLoaded('tenant')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
