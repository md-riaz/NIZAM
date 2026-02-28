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
        return [
            'number' => 'required|string',
            'description' => 'nullable|string',
            'destination_type' => 'required|in:extension,ring_group,ivr,time_condition,voicemail,call_routing_policy,call_flow',
            'destination_id' => 'required|uuid',
            'is_active' => 'boolean',
        ];
    }
}
