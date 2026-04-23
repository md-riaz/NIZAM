<?php

namespace App\Http\Requests;

use App\Services\ExtensionNumberingService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->route('organization');
        $extension = $this->route('extension');

        return [
            'user_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($organization, $extension) {
                    if (! $value) {
                        return;
                    }

                    $user = $organization->users()->find($value);

                    if (! $user) {
                        $fail('Selected user does not belong to this organization.');
                        return;
                    }

                    if ($organization->extensions()->where('user_id', $value)->where('id', '!=', $extension->id)->exists()) {
                        $fail('Selected user already has a personal extension.');
                    }
                },
            ],
            'device_profile_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization, $extension) {
                    if (! $value) {
                        return;
                    }

                    $deviceProfile = $organization->deviceProfiles()->find($value);

                    if (! $deviceProfile) {
                        $fail('Selected device does not belong to this organization.');
                        return;
                    }

                    if ($organization->extensions()->where('device_profile_id', $value)->where('id', '!=', $extension->id)->exists()) {
                        $fail('Selected device already owns an extension.');
                    }
                },
            ],
            'default_outbound_did_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($value && ! $organization->dids()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('Selected default outbound DID is invalid for this organization.');
                    }
                },
            ],
            'default_outbound_gateway_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($value && ! $organization->gateways()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('Selected default outbound gateway is invalid for this organization.');
                    }
                },
            ],
            'allowed_outbound_did_ids' => [
                'sometimes',
                'array',
            ],
            'allowed_outbound_did_ids.*' => [
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if (! $organization->dids()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('Selected allowed outbound DID is invalid for this organization.');
                    }
                },
            ],
            'allowed_outbound_gateway_ids' => [
                'sometimes',
                'array',
            ],
            'allowed_outbound_gateway_ids.*' => [
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if (! $organization->gateways()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('Selected allowed outbound gateway is invalid for this organization.');
                    }
                },
            ],
            'extension' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($organization, $extension) {
                    if ((string) $value !== (string) $extension->extension) {
                        app(ExtensionNumberingService::class)->validate((string) $value, $fail);
                    }

                    if ($organization->extensions()->where('extension', $value)->where('id', '!=', $extension->id)->exists()) {
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

            $allowedDidIds = collect($this->input('allowed_outbound_did_ids', []))->filter();
            $allowedGatewayIds = collect($this->input('allowed_outbound_gateway_ids', []))->filter();
            $defaultDidId = $this->input('default_outbound_did_id');
            $defaultGatewayId = $this->input('default_outbound_gateway_id');

            if ($defaultDidId && ! $allowedDidIds->contains($defaultDidId)) {
                $validator->errors()->add('default_outbound_did_id', 'Default outbound DID must also be listed in allowed outbound DIDs.');
            }

            if ($defaultGatewayId && ! $allowedGatewayIds->contains($defaultGatewayId)) {
                $validator->errors()->add('default_outbound_gateway_id', 'Default outbound gateway must also be listed in allowed outbound gateways.');
            }
        }];
    }
}
