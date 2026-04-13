<?php

namespace App\Services\Messaging;

final readonly class SmsSendResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $sent,
        public ?string $providerMessageId = null,
        public ?string $failureReason = null,
        public array $metadata = [],
    ) {}

    public static function sent(?string $providerMessageId = null, array $metadata = []): self
    {
        return new self(true, $providerMessageId, null, $metadata);
    }

    public static function failed(string $failureReason, array $metadata = []): self
    {
        return new self(false, null, $failureReason, $metadata);
    }
}
