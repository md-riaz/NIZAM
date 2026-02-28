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
        $tenant = $this->route('tenant');
        $did = $this->route('did');

        return [
            'number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($tenant, $did) {
                    $query = $tenant->dids()->where('number', $value);
                    if ($did) {
                        $didId = $did instanceof \App\Models\Did ? $did->id : $did;
                        $query->where('id', '!=', $didId);
                    }
                    if ($query->exists()) {
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
