<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'url' => fake()->url(),
            'events' => fake()->randomElements(
                ['call.created', 'call.answered', 'call.missed', 'call.hangup', 'voicemail.received', 'device.registered'],
                fake()->numberBetween(1, 4)
            ),
            'secret' => Str::random(32),
            'is_active' => true,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
