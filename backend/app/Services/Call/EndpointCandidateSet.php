<?php

namespace App\Services\Call;

use Illuminate\Support\Collection;

final readonly class EndpointCandidateSet
{
    /**
     * @param  list<EndpointCandidate>  $candidates
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $candidates,
        public array $metadata = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->candidates === [];
    }

    /**
     * @return Collection<int, EndpointCandidate>
     */
    public function collect(): Collection
    {
        return collect($this->candidates);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'candidates' => array_map(static fn (EndpointCandidate $candidate) => $candidate->toArray(), $this->candidates),
            'metadata' => $this->metadata,
        ];
    }
}
