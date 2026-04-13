<?php

namespace App\Services\Messaging;

final readonly class SmsRoute
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $provider = null,
        public array $metadata = [],
    ) {}
}
