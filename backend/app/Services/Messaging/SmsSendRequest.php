<?php

namespace App\Services\Messaging;

final readonly class SmsSendRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $tenantDomain,
        public string $from,
        public string $to,
        public string $body,
        public array $metadata = [],
    ) {}
}
