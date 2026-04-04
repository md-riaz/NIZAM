<?php

namespace App\Data;

final readonly class ExtensionData
{
    public function __construct(public array $attributes) {}

    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }
}
