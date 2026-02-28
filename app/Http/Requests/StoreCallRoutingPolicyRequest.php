<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCallRoutingPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'conditions' => 'required|array|min:1',
            'conditions.*.type' => 'required|string|in:time_of_day,day_of_week,caller_id_pattern,blacklist,geo_prefix',
            'conditions.*.params' => 'required|array',
            'match_destination_type' => 'required|string|in:extension,ring_group,ivr,voicemail,call_flow',
            'match_destination_id' => 'required|uuid',
            'no_match_destination_type' => 'nullable|string|in:extension,ring_group,ivr,voicemail,call_flow',
            'no_match_destination_id' => 'nullable|uuid|required_with:no_match_destination_type',
            'priority' => 'integer|min:0|max:1000',
            'is_active' => 'boolean',
        ];
    }
}
