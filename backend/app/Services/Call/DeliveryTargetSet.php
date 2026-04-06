<?php

namespace App\Services\Call;

use Illuminate\Support\Collection;

final readonly class DeliveryTargetSet
{
    /**
     * @param  list<DeliveryTarget>  $targets
     * @param  list<array<string, mixed>>  $sourcePath
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $targets,
        public array $sourcePath = [],
        public array $metadata = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->targets === [];
    }

    /**
     * @return Collection<int, DeliveryTarget>
     */
    public function collect(): Collection
    {
        return collect($this->targets);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'targets' => array_map(static fn (DeliveryTarget $target) => $target->toArray(), $this->targets),
            'source_path' => $this->sourcePath,
            'metadata' => $this->metadata,
        ];
    }
}
