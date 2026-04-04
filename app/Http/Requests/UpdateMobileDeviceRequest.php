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
        $tenant = $this->route('tenant');

        return [
            'extension_id' => [
                'sometimes',
                'uuid',
                function ($attribute, $value, $fail) use ($tenant) {
                    if (! $tenant->extensions()->where('id', $value)->exists()) {
                        $fail('The extension does not belong to this tenant.');
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
