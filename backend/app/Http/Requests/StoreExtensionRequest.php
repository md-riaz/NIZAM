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
            'user_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($organization) {
                    if (! $value) {
                        return;
                    }

                    $user = $organization->users()->find($value);

                    if (! $user) {
                        $fail('Selected user does not belong to this organization.');
                        return;
                    }

                    if ($organization->extensions()->where('user_id', $value)->exists()) {
                        $fail('Selected user already has a personal extension.');
                    }
                },
            ],
            'device_profile_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if (! $value) {
                        return;
                    }

                    $deviceProfile = $organization->deviceProfiles()->find($value);

                    if (! $deviceProfile) {
                        $fail('Selected device does not belong to this organization.');
                        return;
                    }

                    if ($organization->extensions()->where('device_profile_id', $value)->exists()) {
                        $fail('Selected device already owns an extension.');
                    }
                },
            ],
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
            'owner_type' => 'nullable|in:user,device,unassigned',
            'owner_id' => 'nullable|uuid',
            'owner_name' => 'nullable|string',
            'effective_caller_id_name' => 'nullable|string',
            'effective_caller_id_number' => 'nullable|string',
            'outbound_caller_id_name' => 'nullable|string',
            'outbound_caller_id_number' => 'nullable|string',
            'voicemail_enabled' => 'boolean',
            'voicemail_pin' => 'nullable|string|digits_between:4,8',
            'is_active' => 'boolean',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->filled('user_id') && $this->filled('device_profile_id')) {
                $validator->errors()->add('device_profile_id', 'Extension cannot belong to both a user and a device.');
            }
        }];
    }
}
