<?php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnalyticsEvent>
 */
class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'call_uuid' => fake()->unique()->uuid(),
            'version' => 1,
            'wait_time' => fake()->randomFloat(2, 0, 120),
            'talk_time' => fake()->randomFloat(2, 0, 600),
            'abandon' => fake()->boolean(15),
            'agent_id' => fake()->optional()->uuid(),
            'queue_id' => fake()->optional()->uuid(),
            'hangup_cause' => fake()->randomElement(['NORMAL_CLEARING', 'USER_BUSY', 'NO_ANSWER', 'ORIGINATOR_CANCEL']),
            'retries' => fake()->numberBetween(0, 3),
            'webhook_failures' => fake()->numberBetween(0, 5),
            'health_score' => null,
            'score_breakdown' => null,
        ];
    }

    public function abandoned(): static
    {
        return $this->state(fn () => ['abandon' => true]);
    }

    public function scored(): static
    {
        return $this->state(fn () => [
            'health_score' => fake()->randomFloat(2, 0, 100),
            'score_breakdown' => [
                'wait_time_score' => fake()->randomFloat(2, 0, 100),
                'abandon_score' => fake()->randomFloat(2, 0, 100),
                'webhook_score' => fake()->randomFloat(2, 0, 100),
            ],
        ]);
    }
}
