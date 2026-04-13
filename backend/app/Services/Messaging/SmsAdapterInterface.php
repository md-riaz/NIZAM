<?php

namespace App\Services\Messaging;

interface SmsAdapterInterface
{
    public function name(): string;

    public function supportsOutbound(): bool;

    public function send(SmsSendRequest $request): SmsSendResult;
}
