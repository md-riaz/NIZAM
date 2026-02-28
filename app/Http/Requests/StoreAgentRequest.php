<?php

namespace App\Http\Requests;

use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');

        return [
            'extension_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) use ($tenant) {
                    if (! $tenant->extensions()->where('id', $value)->exists()) {
                        $fail('The extension does not belong to this tenant.');
                    }
                    if ($tenant->agents()->where('extension_id', $value)->exists()) {
                        $fail('An agent already exists for this extension.');
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'role' => ['sometimes', Rule::in(Agent::VALID_ROLES)],
            'state' => ['sometimes', Rule::in(Agent::VALID_STATES)],
            'is_active' => 'boolean',
        ];
    }
}
