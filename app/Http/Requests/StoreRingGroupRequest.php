<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRingGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'strategy' => 'in:simultaneous,sequential',
            'ring_timeout' => 'integer|min:5|max:300',
            'members' => 'required|array',
            'fallback_destination_type' => 'nullable|in:extension,ring_group,ivr,time_condition,voicemail|required_with:fallback_destination_id',
            'fallback_destination_id' => 'nullable|uuid|required_with:fallback_destination_type',
            'is_active' => 'boolean',
        ];
    }
}
