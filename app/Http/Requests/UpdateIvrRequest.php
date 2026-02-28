<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIvrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'greet_long' => 'nullable|string',
            'greet_short' => 'nullable|string',
            'timeout' => 'integer|min:1|max:60',
            'max_failures' => 'integer|min:1|max:10',
            'options' => 'required|array',
            'timeout_destination_type' => 'nullable|string|required_with:timeout_destination_id',
            'timeout_destination_id' => 'nullable|uuid|required_with:timeout_destination_type',
            'is_active' => 'boolean',
        ];
    }
}
