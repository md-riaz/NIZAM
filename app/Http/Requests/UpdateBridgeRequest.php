<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
