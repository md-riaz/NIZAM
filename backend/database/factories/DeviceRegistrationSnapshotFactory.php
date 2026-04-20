<?php

namespace Database\Factories;

use App\Models\DeviceRegistrationSnapshot;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceRegistrationSnapshot>
 */
class DeviceRegistrationSnapshotFactory extends Factory
{
    protected $model = DeviceRegistrationSnapshot::class;

    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'endpoint_binding_id' => EndpointBinding::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'extension_id' => Extension::factory()->state(fn (array $attributes): array => [
                'organization_id' => $attributes['organization_id'],
            ]),
            'registration_key' => fake()->bothify('reg-########'),
            'registered' => true,
            'user_agent' => fake()->userAgent(),
            'network_ip' => fake()->ipv4(),
            'observed_at' => now(),
        ];
    }
}
