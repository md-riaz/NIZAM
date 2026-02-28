<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'max_concurrent_calls' => 'integer|min:0',
            'max_dids' => 'integer|min:0',
            'max_ring_groups' => 'integer|min:0',
            'is_active' => 'boolean',
            'status' => ['string', Rule::in(Tenant::VALID_STATUSES)],
        ];
    }
}
