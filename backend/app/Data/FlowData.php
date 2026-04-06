<?php

namespace App\Data;

final readonly class FlowData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public array $definition,
        public bool $publish,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            name: $payload['name'],
            description: $payload['description'] ?? null,
            definition: $payload['version']['definition'] ?? [],
            publish: (bool) ($payload['publish'] ?? false),
        );
    }
}
