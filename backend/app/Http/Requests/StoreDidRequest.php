<?php

namespace App\Http\Requests;

use App\Rules\DidDestination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'gateway_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($value && ! $organization->gateways()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected gateway is invalid for this organization.');
                    }
                },
            ],
            'description' => 'nullable|string',
            'recording_policy' => ['string', Rule::in(['inherit', 'off', 'all', 'incoming', 'outgoing'])],
            // A number routes to an extension or a flow, nothing else: the flow
            // is what decides a ring group, queue, or time condition.
            'destination_type' => ['required', Rule::in(DidDestination::TYPES)],
            'destination_id' => [
                'required',
                'uuid',
                new DidDestination($organization, $this->input('destination_type')),
            ],
            'is_active' => 'boolean',
        ];
    }
}
