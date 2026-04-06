<?php

namespace App\Services\Call;

final readonly class ReachabilityDecision
{
    public const STATUS_ONLINE_SIP = 'online_sip';

    public const STATUS_DORMANT_PUSH = 'dormant_push';

    public const STATUS_PSTN_ELIGIBLE = 'pstn_eligible';

    public const STATUS_UNAVAILABLE = 'unavailable';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $endpointBindingId,
        public string $status,
        public bool $canRingNow,
        public bool $shouldSendPush,
        public ?string $allowLateJoinWindowUntil,
        public bool $shouldOfferPstn,
        public string $decisionReason,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'endpoint_binding_id' => $this->endpointBindingId,
            'status' => $this->status,
            'can_ring_now' => $this->canRingNow,
            'should_send_push' => $this->shouldSendPush,
            'allow_late_join_window_until' => $this->allowLateJoinWindowUntil,
            'should_offer_pstn' => $this->shouldOfferPstn,
            'decision_reason' => $this->decisionReason,
            'metadata' => $this->metadata,
        ];
    }
}
