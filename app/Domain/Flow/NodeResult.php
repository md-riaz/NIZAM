<?php

namespace App\Domain\Flow;

class NodeResult
{
    public function __construct(
        public readonly ?string $transition = null,
        public readonly array $payload = [],
        public readonly ?string $waitForEvent = null,
        public readonly bool $completed = false,
    ) {}

    public static function transition(string $transition, array $payload = []): self
    {
        return new self(transition: $transition, payload: $payload);
    }

    public static function wait(string $event, array $payload = []): self
    {
        return new self(payload: $payload, waitForEvent: $event);
    }

    public static function complete(array $payload = []): self
    {
        return new self(payload: $payload, completed: true);
    }
}
