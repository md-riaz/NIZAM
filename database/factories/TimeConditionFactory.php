<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TimeCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeCondition>
 */
class TimeConditionFactory extends Factory
{
    protected $model = TimeCondition::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement([
                'Business Hours', 'Weekend Hours', 'Holiday Schedule',
                'After Hours', 'Lunch Break', 'Night Shift',
            ]) . ' ' . fake()->numerify('##'),
            'conditions' => [
                [
                    'wday' => 'mon-fri',
                    'time_from' => '09:00',
                    'time_to' => '17:00',
                ],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => fake()->uuid(),
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => fake()->uuid(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
