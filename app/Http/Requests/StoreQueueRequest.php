<?php

namespace App\Http\Requests;

use App\Models\Queue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($tenant) {
                    if ($tenant->queues()->where('name', $value)->exists()) {
                        $fail('A queue with this name already exists for this tenant.');
                    }
                },
            ],
            'strategy' => ['sometimes', Rule::in(Queue::VALID_STRATEGIES)],
            'max_wait_time' => 'sometimes|integer|min:10|max:3600',
            'overflow_action' => ['sometimes', Rule::in(Queue::VALID_OVERFLOW_ACTIONS)],
            'overflow_destination' => 'nullable|string|max:255',
            'music_on_hold' => 'nullable|string|max:255',
            'service_level_threshold' => 'sometimes|integer|min:1|max:300',
            'is_active' => 'boolean',
        ];
    }
}
