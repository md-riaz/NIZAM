<?php

namespace App\Services\Call;

final readonly class OfferCommandResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $executed,
        public ?string $response = null,
        public ?string $legUuid = null,
        public ?string $failureReason = null,
        public array $metadata = [],
    ) {}

    public static function success(?string $response = null, ?string $legUuid = null, array $metadata = []): self
    {
        return new self(true, $response, $legUuid, null, $metadata);
    }

    public static function failure(string $failureReason, ?string $response = null, array $metadata = []): self
    {
        return new self(false, $response, null, $failureReason, $metadata);
    }
}
