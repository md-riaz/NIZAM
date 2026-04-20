<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'nullable|uuid|exists:organizations,id',
            'name' => 'required|string|max:255',
            'vendor' => 'nullable|string|max:100',
            'host' => 'required|string|max:255',
            'port' => 'integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'realm' => 'nullable|string|max:255',
            'transport' => 'string|in:udp,tcp,tls',
            'register' => 'boolean',
            'proxy' => 'nullable|string|max:255',
            'register_proxy' => 'nullable|string|max:255',
            'from_domain' => 'nullable|string|max:255',
            'extension' => 'nullable|string|max:255',
            'inbound_codecs' => 'nullable|array',
            'inbound_codecs.*' => 'string|in:OPUS,PCMU,PCMA,G722,G729,G726,G726-32,iLBC,SPEEX,VP8,H264',
            'outbound_codecs' => 'nullable|array',
            'outbound_codecs.*' => 'string|in:OPUS,PCMU,PCMA,G722,G729,G726,G726-32,iLBC,SPEEX,VP8,H264',
            'preferred_codecs' => 'nullable|array',
            'preferred_codecs.*' => 'string|in:OPUS,PCMU,PCMA,G722,G729,G726,G726-32,iLBC,SPEEX,VP8,H264',
            'dtmf_mode' => 'nullable|string|in:rfc2833,info,inband',
            'srtp_mode' => 'nullable|string|in:none,optional,required',
            'allow_transcoding' => 'boolean',
            'expire_seconds' => 'integer|min:30|max:86400',
            'retry_seconds' => 'integer|min:1|max:3600',
            'caller_id_in_from' => 'boolean',
            'profile' => 'string|max:64',
            'is_active' => 'boolean',
        ];
    }
}
