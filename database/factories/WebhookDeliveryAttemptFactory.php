<?php

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookDeliveryAttempt>
 */
class WebhookDeliveryAttemptFactory extends Factory
{
    protected $model = WebhookDeliveryAttempt::class;

    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event_type' => fake()->randomElement(['call.started', 'call.answered', 'call.hangup']),
            'payload' => ['call_uuid' => fake()->uuid(), 'caller' => '+15551234567'],
            'response_status' => 200,
            'response_body' => null,
            'attempt' => 1,
            'success' => true,
            'error_message' => null,
            'delivered_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'response_status' => 500,
            'success' => false,
            'error_message' => 'Internal Server Error',
        ]);
    }
}
