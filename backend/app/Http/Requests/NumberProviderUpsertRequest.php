<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NumberProviderUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'register' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
