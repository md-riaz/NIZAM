<?php

namespace Database\Factories;

use App\Models\RingGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RingGroup>
 */
class RingGroupFactory extends Factory
{
    protected $model = RingGroup::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement([
                'Sales Team', 'Support', 'Billing', 'Engineering', 'Front Desk',
                'Operations', 'Management', 'Help Desk', 'Customer Service',
            ]).' '.fake()->numerify('##'),
            'strategy' => fake()->randomElement(['simultaneous', 'sequential']),
            'ring_timeout' => fake()->numberBetween(15, 60),
            'members' => array_map(
                fn () => fake()->uuid(),
                range(1, fake()->numberBetween(1, 5))
            ),
            'fallback_destination_type' => fake()->optional(0.5)->randomElement([
                'extension', 'voicemail',
            ]),
            'fallback_destination_id' => fake()->optional(0.5)->uuid(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
