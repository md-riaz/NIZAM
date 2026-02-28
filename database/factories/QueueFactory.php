<?php

namespace Database\Factories;

use App\Models\Queue;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Queue>
 */
class QueueFactory extends Factory
{
    protected $model = Queue::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(2, true).' Queue',
            'strategy' => Queue::STRATEGY_ROUND_ROBIN,
            'max_wait_time' => 300,
            'overflow_action' => Queue::OVERFLOW_VOICEMAIL,
            'overflow_destination' => null,
            'music_on_hold' => null,
            'service_level_threshold' => 20,
            'is_active' => true,
        ];
    }

    public function ringAll(): static
    {
        return $this->state(fn () => ['strategy' => Queue::STRATEGY_RING_ALL]);
    }

    public function leastRecent(): static
    {
        return $this->state(fn () => ['strategy' => Queue::STRATEGY_LEAST_RECENT]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
