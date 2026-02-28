<?php

namespace Database\Factories;

use App\Models\CallRoutingPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CallRoutingPolicy>
 */
class CallRoutingPolicyFactory extends Factory
{
    protected $model = CallRoutingPolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true).' policy',
            'description' => fake()->optional()->sentence(),
            'conditions' => [
                [
                    'type' => 'time_of_day',
                    'params' => ['start' => '09:00', 'end' => '17:00'],
                ],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => Str::uuid()->toString(),
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => Str::uuid()->toString(),
            'priority' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
