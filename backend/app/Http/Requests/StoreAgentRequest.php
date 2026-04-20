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
        $organization = $this->route('organization');

        return [
            'extension_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if (! $organization->extensions()->where('id', $value)->exists()) {
                        $fail('The extension does not belong to this organization.');
                    }
                    if ($organization->agents()->where('extension_id', $value)->exists()) {
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
