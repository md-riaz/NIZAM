<?php

namespace App\Http\Requests;

use App\Services\Flow\FlowValidationService;
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
            'nodes.*.type' => 'required|string|in:start,schedule_check,business_hours,menu,ring_team,voicemail,hangup,end',
            'nodes.*.config' => 'nullable|array',
            'nodes.*.edges' => 'nullable|array',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $nodes = $this->input('nodes', []);
            $errors = app(FlowValidationService::class)->validateDefinition($nodes);

            foreach ($errors as $path => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($path, $message);
                }
            }
        });
    }
}
