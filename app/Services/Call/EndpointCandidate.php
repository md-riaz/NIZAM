<?php

namespace App\Services\Call;

final readonly class EndpointCandidate
{
    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    public function __construct(
        public string $endpointBindingId,
        public string $ownerType,
        public string $ownerId,
        public string $candidateType,
        public ?string $sipAor,
        public bool $pushCapable,
        public bool $allowLateJoinAfterPush,
        public ?string $forwardNumber,
        public bool $forwardRequiresConfirm,
        public int $priority,
        public array $sourcePath = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'endpoint_binding_id' => $this->endpointBindingId,
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'candidate_type' => $this->candidateType,
            'sip_aor' => $this->sipAor,
            'push_capable' => $this->pushCapable,
            'allow_late_join_after_push' => $this->allowLateJoinAfterPush,
            'forward_number' => $this->forwardNumber,
            'forward_requires_confirm' => $this->forwardRequiresConfirm,
            'priority' => $this->priority,
            'source_path' => $this->sourcePath,
        ];
    }
}
