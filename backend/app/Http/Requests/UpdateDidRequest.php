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
            'description' => 'nullable|string',
            'destination_type' => 'required|in:extension,ring_group,ivr,voicemail,time_condition,call_routing_policy,flow,bridge',
            'destination_id' => 'required|uuid',
            'is_active' => 'boolean',
        ];
    }
}
