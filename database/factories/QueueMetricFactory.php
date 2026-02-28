<?php

namespace Database\Factories;

use App\Models\Queue;
use App\Models\QueueMetric;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QueueMetric>
 */
class QueueMetricFactory extends Factory
{
    protected $model = QueueMetric::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'queue_id' => Queue::factory(),
            'period' => QueueMetric::PERIOD_HOURLY,
            'period_start' => now()->startOfHour(),
            'calls_offered' => fake()->numberBetween(0, 100),
            'calls_answered' => fake()->numberBetween(0, 80),
            'calls_abandoned' => fake()->numberBetween(0, 20),
            'average_wait_time' => fake()->randomFloat(2, 0, 120),
            'max_wait_time' => fake()->randomFloat(2, 0, 300),
            'service_level' => fake()->randomFloat(2, 50, 100),
            'abandon_rate' => fake()->randomFloat(2, 0, 50),
            'agent_occupancy' => fake()->randomFloat(2, 20, 95),
        ];
    }
}
