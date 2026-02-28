<?php

namespace Database\Factories;

use App\Models\AlertPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AlertPolicy>
 */
class AlertPolicyFactory extends Factory
{
    protected $model = AlertPolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true).' alert',
            'metric' => fake()->randomElement(AlertPolicy::VALID_METRICS),
            'condition' => AlertPolicy::CONDITION_GT,
            'threshold' => fake()->randomFloat(2, 10, 90),
            'window_minutes' => 60,
            'channels' => [AlertPolicy::CHANNEL_EMAIL],
            'recipients' => ['alerts@example.com'],
            'is_active' => true,
            'cooldown_minutes' => 15,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
