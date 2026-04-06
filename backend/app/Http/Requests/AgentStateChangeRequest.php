<?php

namespace App\Http\Requests;

use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentStateChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state' => ['required', Rule::in(Agent::VALID_STATES)],
            'pause_reason' => [
                'nullable',
                'string',
                'max:255',
                'required_if:state,paused',
            ],
        ];
    }
}
