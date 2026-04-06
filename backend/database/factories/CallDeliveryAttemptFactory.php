<?php

namespace Database\Factories;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CallDeliveryAttempt>
 */
class CallDeliveryAttemptFactory extends Factory
{
    protected $model = CallDeliveryAttempt::class;

    public function definition(): array
    {
        return [
            'call_session_id' => CallSession::query()->inRandomOrder()->value('id') ?? CallSession::factory(),
            'endpoint_binding_id' => EndpointBinding::factory(),
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_PLANNED,
            'freeswitch_leg_uuid' => fake()->uuid(),
            'started_at' => now(),
            'answered_at' => null,
            'ended_at' => null,
            'failure_reason' => null,
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function forCallSession(CallSession $callSession): static
    {
        return $this->state(fn () => [
            'call_session_id' => $callSession->id,
        ]);
    }

    public function forEndpointBinding(EndpointBinding $endpointBinding): static
    {
        return $this->state(fn () => [
            'endpoint_binding_id' => $endpointBinding->id,
        ]);
    }

    public function won(): static
    {
        return $this->state(fn () => [
            'status' => CallDeliveryAttempt::STATUS_WON,
            'answered_at' => now(),
            'ended_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => CallDeliveryAttempt::STATUS_RINGING,
            'ended_at' => null,
        ]);
    }
}
