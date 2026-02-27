<?php

namespace Database\Factories;

use App\Models\Did;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Did>
 */
class DidFactory extends Factory
{
    protected $model = Did::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'number' => '+1' . fake()->numerify('##########'),
            'description' => fake()->optional(0.7)->sentence(),
            'destination_type' => fake()->randomElement([
                'extension', 'ring_group', 'ivr', 'time_condition', 'voicemail',
            ]),
            'destination_id' => fake()->uuid(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
