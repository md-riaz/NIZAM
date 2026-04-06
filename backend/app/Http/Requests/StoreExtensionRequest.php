<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');

        return [
            'extension' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($tenant) {
                    if ($tenant->extensions()->where('extension', $value)->exists()) {
                        $fail('The extension has already been taken for this tenant.');
                    }
                },
            ],
            'password' => 'required|string|min:8',
            'directory_first_name' => 'required|string',
            'directory_last_name' => 'required|string',
            'effective_caller_id_name' => 'nullable|string',
            'effective_caller_id_number' => 'nullable|string',
            'outbound_caller_id_name' => 'nullable|string',
            'outbound_caller_id_number' => 'nullable|string',
            'voicemail_enabled' => 'boolean',
            'voicemail_pin' => 'nullable|string|digits_between:4,8',
            'is_active' => 'boolean',
        ];
    }
}
