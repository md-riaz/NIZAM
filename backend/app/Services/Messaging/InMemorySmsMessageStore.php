<?php

namespace App\Services\Messaging;

class InMemorySmsMessageStore implements SmsMessageStoreInterface
{
    /**
     * @var array<string, SmsMessageRecord>
     */
    private array $messages = [];

    public function store(SmsMessageRecord $message): SmsMessageRecord
    {
        $this->messages[$message->id] = $message;

        return $message;
    }

    public function forTenant(string $tenantDomain): array
    {
        return array_values(array_filter(
            $this->messages,
            fn (SmsMessageRecord $message): bool => $message->tenantDomain === $tenantDomain,
        ));
    }
}
