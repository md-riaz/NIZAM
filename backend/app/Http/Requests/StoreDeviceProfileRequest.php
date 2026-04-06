<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'vendor' => 'required|string',
            'mac_address' => 'nullable|string',
            'template' => 'nullable|string',
            'extension_id' => 'nullable|uuid',
            'is_active' => 'boolean',
        ];
    }
}
