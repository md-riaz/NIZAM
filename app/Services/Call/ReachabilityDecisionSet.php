<?php

namespace App\Services\Call;

use Illuminate\Support\Collection;

final readonly class ReachabilityDecisionSet
{
    /**
     * @param  list<ReachabilityDecision>  $decisions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $decisions,
        public array $metadata = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->decisions === [];
    }

    /**
     * @return Collection<int, ReachabilityDecision>
     */
    public function collect(): Collection
    {
        return collect($this->decisions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'decisions' => array_map(static fn (ReachabilityDecision $decision) => $decision->toArray(), $this->decisions),
            'metadata' => $this->metadata,
        ];
    }
}
