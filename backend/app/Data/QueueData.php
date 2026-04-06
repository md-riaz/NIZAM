<?php

namespace App\Data;

final readonly class QueueData
{
    public function __construct(public array $attributes) {}

    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }
}
