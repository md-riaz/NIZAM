<?php

namespace App\Services\Messaging;

use DateTimeImmutable;

final readonly class SmsMessageRecord
{
    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $organizationDomain,
        public string $direction,
        public string $from,
        public string $to,
        public string $body,
        public string $status,
        public ?string $provider = null,
        public ?string $providerMessageId = null,
        public ?string $failureReason = null,
        public array $metadata = [],
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
