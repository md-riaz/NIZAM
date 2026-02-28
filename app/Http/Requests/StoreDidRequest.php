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
        $tenant = $this->route('tenant');

        return [
            'number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($tenant) {
                    if ($tenant->dids()->where('number', $value)->exists()) {
                        $fail('The DID number has already been taken for this tenant.');
                    }
                },
            ],
            'description' => 'nullable|string',
            'destination_type' => 'required|in:extension,ring_group,ivr,time_condition,voicemail,call_routing_policy,call_flow',
            'destination_id' => 'required|uuid',
            'is_active' => 'boolean',
        ];
    }
}
