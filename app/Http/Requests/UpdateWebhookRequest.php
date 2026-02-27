<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => 'sometimes|url|max:2048',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string|in:call.started,call.answered,call.missed,call.hangup,voicemail.received,device.registered',
            'secret' => 'nullable|string|min:16',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
