<?php

namespace App\Services\Call;

final readonly class DeliveryTarget
{
    /**
     * @param  list<array<string, mixed>>  $sourcePath
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $type,
        public string $id,
        public array $sourcePath = [],
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'source_path' => $this->sourcePath,
            'metadata' => $this->metadata,
        ];
    }
}
