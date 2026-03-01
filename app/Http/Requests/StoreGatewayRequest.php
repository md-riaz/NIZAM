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
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'realm' => 'nullable|string|max:255',
            'transport' => 'string|in:udp,tcp,tls',
            'inbound_codecs' => 'nullable|array',
            'inbound_codecs.*' => 'string',
            'outbound_codecs' => 'nullable|array',
            'outbound_codecs.*' => 'string',
            'allow_transcoding' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
