<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants',
            'slug' => 'required|string|unique:tenants|alpha_dash',
            'max_extensions' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
