<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($organization->dids()->where('number', $value)->exists()) {
                        $fail('The DID number has already been taken for this organization.');
                    }
                },
            ],
            'description' => 'nullable|string',
            'destination_type' => 'required|in:extension,ring_group,ivr,voicemail,time_condition,call_routing_policy,flow,bridge',
            'destination_id' => 'required|uuid',
            'is_active' => 'boolean',
        ];
    }
}
