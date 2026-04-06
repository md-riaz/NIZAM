<?php

namespace App\Services\Call;

final readonly class DeliveryPlanItem
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public EndpointCandidate $candidate,
        public ReachabilityDecision $decision,
        public string $wave,
        public string $attemptType,
        public int $delaySeconds = 0,
        public bool $requiresConfirmation = false,
        public ?string $lateJoinWindowUntil = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'wave' => $this->wave,
            'attempt_type' => $this->attemptType,
            'delay_seconds' => $this->delaySeconds,
            'requires_confirmation' => $this->requiresConfirmation,
            'late_join_window_until' => $this->lateJoinWindowUntil,
            'candidate' => $this->candidate->toArray(),
            'decision' => $this->decision->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
