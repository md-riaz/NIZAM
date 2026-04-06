<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBridgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'bridge_type' => 'required|string|in:gateway,raw',
            'gateway_id' => 'nullable|uuid',
            'destination_template' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('bridge_type');
            $gatewayId = $this->input('gateway_id');

            if ($type === 'gateway' && ! $gatewayId) {
                $validator->errors()->add('gateway_id', 'The gateway_id field is required when bridge_type is gateway.');
            }

            if ($type === 'raw' && $gatewayId) {
                $validator->errors()->add('gateway_id', 'The gateway_id field must be empty when bridge_type is raw.');
            }
        });
    }
}
