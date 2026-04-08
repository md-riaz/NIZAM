<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBridgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'bridge_type' => 'sometimes|string|in:gateway,raw',
            'gateway_id' => 'nullable|uuid',
            'destination_template' => 'sometimes|string|max:255',
            'codec_policy' => 'sometimes|string|in:default,restricted,preferred,exact,inherit',
            'codec_list' => 'nullable|array',
            'codec_list.*' => 'string|in:OPUS,PCMU,PCMA,G722,G729,G726,G726-32,iLBC,SPEEX,VP8,H264',
            'transcode_policy' => 'sometimes|string|in:none,allow,web_only',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('bridge_type', $this->route('bridge')?->bridge_type);
            $gatewayIdProvided = $this->exists('gateway_id');
            $gatewayId = $gatewayIdProvided ? $this->input('gateway_id') : $this->route('bridge')?->gateway_id;

            if ($type === 'gateway' && ! $gatewayId) {
                $validator->errors()->add('gateway_id', 'The gateway_id field is required when bridge_type is gateway.');
            }

            if ($type === 'raw' && $gatewayIdProvided && $this->input('gateway_id')) {
                $validator->errors()->add('gateway_id', 'The gateway_id field must be empty when bridge_type is raw.');
            }
        });
    }
}
