<?php

namespace Database\Factories;

use App\Models\Ivr;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ivr>
 */
class IvrFactory extends Factory
{
    protected $model = Ivr::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement([
                'Main Menu', 'Support Menu', 'Sales Menu', 'After Hours Menu',
                'Billing Menu', 'Directory', 'Welcome Menu',
            ]).' '.fake()->numerify('##'),
            'greet_long' => fake()->optional(0.6)->filePath(),
            'greet_short' => fake()->optional(0.6)->filePath(),
            'timeout' => fake()->numberBetween(3, 10),
            'max_failures' => fake()->numberBetween(1, 5),
            'options' => array_map(fn (int $digit) => [
                'digit' => (string) $digit,
                'destination_type' => fake()->randomElement(['extension', 'ring_group', 'ivr', 'voicemail']),
                'destination_id' => fake()->uuid(),
            ], range(1, fake()->numberBetween(1, 5))),
            'timeout_destination_type' => fake()->optional(0.5)->randomElement([
                'extension', 'voicemail',
            ]),
            'timeout_destination_id' => fake()->optional(0.5)->uuid(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
