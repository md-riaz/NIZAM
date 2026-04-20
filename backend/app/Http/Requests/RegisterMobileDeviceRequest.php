<?php

namespace App\Http\Requests;

use App\Models\EndpointBinding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMobileDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'extension_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if (! $organization->extensions()->where('id', $value)->exists()) {
                        $fail('The extension does not belong to this organization.');
                    }
                },
            ],
            'device_uuid' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(EndpointBinding::VALID_PLATFORMS)],
            'push_token' => ['nullable', 'string', 'required_if:push_enabled,true'],
            'voip_push_token' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'push_enabled' => ['sometimes', 'boolean'],
            'sip_background_mode_supported' => ['sometimes', 'boolean'],
            'allow_late_join_after_push' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
