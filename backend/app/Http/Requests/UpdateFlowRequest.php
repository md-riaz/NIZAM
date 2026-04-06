<?php

namespace App\Http\Requests;

use App\Services\Flow\FlowValidationService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'version.definition.nodes' => 'nullable|array|min:1',
            'version.definition.edges' => 'nullable|array',
            'publish' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $nodes = $this->input('version.definition.nodes', []);

            if ($nodes === []) {
                return;
            }

            $errors = app(FlowValidationService::class)->validateDefinition($nodes);

            foreach ($errors as $path => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add('version.definition.'.$path, $message);
                }
            }
        });
    }
}
