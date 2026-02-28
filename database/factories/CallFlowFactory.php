<?php

namespace Database\Factories;

use App\Models\CallFlow;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CallFlow>
 */
class CallFlowFactory extends Factory
{
    protected $model = CallFlow::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true).' flow',
            'description' => fake()->optional()->sentence(),
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'play_prompt',
                    'data' => ['file' => 'welcome.wav'],
                    'next' => 'bridge1',
                ],
                [
                    'id' => 'bridge1',
                    'type' => 'bridge',
                    'data' => ['destination_type' => 'extension', 'destination_id' => fake()->uuid()],
                    'next' => null,
                ],
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
