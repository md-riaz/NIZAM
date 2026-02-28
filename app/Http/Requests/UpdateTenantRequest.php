<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'domain' => 'required|string|unique:tenants,domain,'.$tenantId,
            'slug' => 'required|string|alpha_dash|unique:tenants,slug,'.$tenantId,
            'max_extensions' => 'integer|min:0',
            'max_concurrent_calls' => 'integer|min:0',
            'max_dids' => 'integer|min:0',
            'max_ring_groups' => 'integer|min:0',
            'is_active' => 'boolean',
            'status' => ['string', Rule::in(Tenant::VALID_STATUSES)],
        ];
    }
}
