<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:call.started,call.answered,call.missed,call.hangup,call.bridge,recording.created,voicemail.received,device.registered,extension.created,extension.updated,extension.deleted,did.created,did.updated,did.deleted,tenant.updated',
            'secret' => 'nullable|string|min:16',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
