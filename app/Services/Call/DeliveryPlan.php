<?php

namespace App\Services\Call;

use Illuminate\Support\Collection;

final readonly class DeliveryPlan
{
    /**
     * @param  list<DeliveryPlanItem>  $immediateSipWave
     * @param  list<DeliveryPlanItem>  $immediatePushWave
     * @param  list<DeliveryPlanItem>  $delayedPstnWave
     * @param  array<string, mixed>  $wakeWindow
     * @param  array<string, mixed>  $cancellationPolicy
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $callSessionId,
        public int $wakeWindowSeconds,
        public array $immediateSipWave = [],
        public array $immediatePushWave = [],
        public array $delayedPstnWave = [],
        public array $wakeWindow = [],
        public array $cancellationPolicy = [],
        public array $metadata = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->immediateSipWave === []
            && $this->immediatePushWave === []
            && $this->delayedPstnWave === [];
    }

    /**
     * @return Collection<int, DeliveryPlanItem>
     */
    public function collect(): Collection
    {
        return collect([
            ...$this->immediateSipWave,
            ...$this->immediatePushWave,
            ...$this->delayedPstnWave,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'call_session_id' => $this->callSessionId,
            'wake_window_seconds' => $this->wakeWindowSeconds,
            'immediate_sip_wave' => array_map(static fn (DeliveryPlanItem $item) => $item->toArray(), $this->immediateSipWave),
            'immediate_push_wave' => array_map(static fn (DeliveryPlanItem $item) => $item->toArray(), $this->immediatePushWave),
            'delayed_pstn_wave' => array_map(static fn (DeliveryPlanItem $item) => $item->toArray(), $this->delayedPstnWave),
            'wake_window' => $this->wakeWindow,
            'cancellation_policy' => $this->cancellationPolicy,
            'metadata' => $this->metadata,
        ];
    }
}
