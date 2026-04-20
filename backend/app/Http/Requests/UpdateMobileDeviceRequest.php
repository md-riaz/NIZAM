<?php

namespace App\Http\Requests;

use App\Models\EndpointBinding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMobileDeviceRequest extends FormRequest
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
                'sometimes',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if (! $organization->extensions()->where('id', $value)->exists()) {
                        $fail('The extension does not belong to this organization.');
                    }
                },
            ],
            'platform' => ['sometimes', Rule::in(EndpointBinding::VALID_PLATFORMS)],
            'push_token' => ['sometimes', 'nullable', 'string', 'required_if:push_enabled,true'],
            'voip_push_token' => ['sometimes', 'nullable', 'string'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'push_enabled' => ['sometimes', 'boolean'],
            'sip_background_mode_supported' => ['sometimes', 'boolean'],
            'allow_late_join_after_push' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
