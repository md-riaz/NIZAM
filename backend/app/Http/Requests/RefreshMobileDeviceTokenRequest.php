<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefreshMobileDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'push_token' => ['sometimes', 'nullable', 'string', 'required_without:voip_push_token', 'required_if:push_enabled,true'],
            'voip_push_token' => ['sometimes', 'nullable', 'string', 'required_without:push_token'],
            'push_enabled' => ['sometimes', 'boolean'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
