<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'conditions' => 'required|array',
            'match_destination_type' => 'nullable|string|required_with:match_destination_id',
            'match_destination_id' => 'nullable|uuid|required_with:match_destination_type',
            'no_match_destination_type' => 'nullable|string|required_with:no_match_destination_id',
            'no_match_destination_id' => 'nullable|uuid|required_with:no_match_destination_type',
            'is_active' => 'boolean',
        ];
    }
}
