<?php

namespace App\Services\Messaging;

class TelnyxSmsAdapter implements SmsAdapterInterface
{
    public function name(): string
    {
        return 'telnyx';
    }

    public function supportsOutbound(): bool
    {
        return true;
    }

    public function send(SmsSendRequest $request): SmsSendResult
    {
        return SmsSendResult::failed('telnyx_adapter_not_configured', [
            'provider' => $this->name(),
            'to' => $request->to,
        ]);
    }
}
