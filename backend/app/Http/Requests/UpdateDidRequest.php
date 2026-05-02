<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->route('organization');
        $did = $this->route('did');

        return [
            'number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($organization, $did) {
                    $query = $organization->dids()->where('number', $value);
                    if ($did) {
                        $didId = $did instanceof \App\Models\Did ? $did->id : $did;
                        $query->where('id', '!=', $didId);
                    }
                    if ($query->exists()) {
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
            'destination_type' => 'required|in:extension,flow',
            'destination_id' => 'required|uuid',
            'is_active' => 'boolean',
        ];
    }

}
