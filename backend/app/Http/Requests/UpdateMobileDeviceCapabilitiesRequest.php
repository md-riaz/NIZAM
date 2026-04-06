<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMobileDeviceCapabilitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'push_enabled' => ['sometimes', 'boolean'],
            'sip_background_mode_supported' => ['sometimes', 'boolean'],
            'allow_late_join_after_push' => ['sometimes', 'boolean'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
