<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCallFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'nodes' => 'required|array|min:1',
            'nodes.*.id' => 'required|string|max:100',
            'nodes.*.type' => 'required|string|in:play_prompt,collect_input,branch,api_call,bridge,record,webhook',
            'nodes.*.data' => 'required|array',
            'nodes.*.next' => 'nullable',
            'is_active' => 'boolean',
        ];
    }
}
