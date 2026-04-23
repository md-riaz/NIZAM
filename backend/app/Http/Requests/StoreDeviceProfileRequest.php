<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'vendor' => 'required|string',
            'mac_address' => 'nullable|string',
            'template' => 'nullable|string',
            'extension_id' => 'nullable|uuid',
            'default_outbound_did_id' => 'nullable|uuid',
            'phone_number_ids' => 'nullable|array',
            'phone_number_ids.*' => 'uuid',
            'is_active' => 'boolean',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $organization = $this->route('organization');

            foreach (($this->input('phone_number_ids') ?? []) as $didId) {
                if (! $organization->dids()->whereKey($didId)->exists()) {
                    $validator->errors()->add('phone_number_ids', 'Selected phone number must belong to this organization.');
                    break;
                }
            }

            $defaultDidId = $this->input('default_outbound_did_id');
            if ($defaultDidId && ! in_array($defaultDidId, $this->input('phone_number_ids') ?? [], true)) {
                $validator->errors()->add('default_outbound_did_id', 'Default outbound number must be granted to this device.');
            }
        }];
    }
}
