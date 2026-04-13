<?php

namespace App\Services\Messaging;

class SignalWireSmsAdapter implements SmsAdapterInterface
{
    public function name(): string
    {
        return 'signalwire';
    }

    public function supportsOutbound(): bool
    {
        return true;
    }

    public function send(SmsSendRequest $request): SmsSendResult
    {
        return SmsSendResult::failed('signalwire_adapter_not_configured', [
            'provider' => $this->name(),
            'to' => $request->to,
        ]);
    }
}
