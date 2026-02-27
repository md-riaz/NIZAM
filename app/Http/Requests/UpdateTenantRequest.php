<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('tenant')->id;

        return [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants,domain,' . $tenantId,
            'slug' => 'required|string|alpha_dash|unique:tenants,slug,' . $tenantId,
            'max_extensions' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
