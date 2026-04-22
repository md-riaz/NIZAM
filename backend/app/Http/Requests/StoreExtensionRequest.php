<?php

namespace App\Http\Requests;

use App\Services\ExtensionNumberingService;
use Illuminate\Foundation\Http\FormRequest;

class StoreExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'extension' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($organization) {
                    app(ExtensionNumberingService::class)->validate((string) $value, $fail);

                    if ($organization->extensions()->where('extension', $value)->exists()) {
                        $fail('The extension has already been taken for this organization.');
                    }
                },
            ],
            'password' => 'required|string|min:8',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
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
