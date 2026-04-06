<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileDeviceHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'last_seen_at' => ['sometimes', 'date'],
            'last_registered_at' => ['sometimes', 'nullable', 'date'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
