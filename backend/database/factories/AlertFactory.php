<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\AlertPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'alert_policy_id' => AlertPolicy::factory(),
            'severity' => fake()->randomElement(Alert::VALID_SEVERITIES),
            'metric' => fake()->randomElement(AlertPolicy::VALID_METRICS),
            'current_value' => fake()->randomFloat(2, 0, 100),
            'threshold_value' => fake()->randomFloat(2, 0, 100),
            'status' => Alert::STATUS_OPEN,
            'message' => fake()->sentence(),
            'context' => ['window_minutes' => 60],
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => Alert::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
    }
}
