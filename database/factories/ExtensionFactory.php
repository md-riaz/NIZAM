<?php

namespace Database\Factories;

use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Extension>
 */
class ExtensionFactory extends Factory
{
    protected $model = Extension::class;

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'tenant_id' => Tenant::factory(),
            'extension' => (string) fake()->numberBetween(1000, 9999),
            'password' => fake()->password(8, 20),
            'directory_first_name' => $firstName,
            'directory_last_name' => $lastName,
            'effective_caller_id_name' => fake()->optional(0.7)->name(),
            'effective_caller_id_number' => fake()->optional(0.7)->e164PhoneNumber(),
            'outbound_caller_id_name' => fake()->optional(0.5)->company(),
            'outbound_caller_id_number' => fake()->optional(0.5)->e164PhoneNumber(),
            'voicemail_enabled' => fake()->boolean(70),
            'voicemail_pin' => fake()->optional(0.6)->numerify(
                str_repeat('#', fake()->numberBetween(4, 8))
            ),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
