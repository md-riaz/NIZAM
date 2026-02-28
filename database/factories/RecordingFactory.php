<?php

namespace Database\Factories;

use App\Models\Recording;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recording>
 */
class RecordingFactory extends Factory
{
    protected $model = Recording::class;

    public function definition(): array
    {
        $uuid = fake()->unique()->uuid();

        return [
            'tenant_id' => Tenant::factory(),
            'call_uuid' => $uuid,
            'file_path' => "recordings/{$uuid}.wav",
            'file_name' => "{$uuid}.wav",
            'file_size' => fake()->numberBetween(10000, 5000000),
            'format' => 'wav',
            'duration' => fake()->numberBetween(5, 600),
            'direction' => fake()->randomElement(['inbound', 'outbound', 'local']),
            'caller_id_number' => '+1'.fake()->numerify('##########'),
            'destination_number' => (string) fake()->numberBetween(1000, 9999),
        ];
    }
}
