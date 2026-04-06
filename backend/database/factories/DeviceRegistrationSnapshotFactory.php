<?php

namespace Database\Factories;

use App\Models\DeviceRegistrationSnapshot;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceRegistrationSnapshot>
 */
class DeviceRegistrationSnapshotFactory extends Factory
{
    protected $model = DeviceRegistrationSnapshot::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'endpoint_binding_id' => EndpointBinding::factory()->state(fn (array $attributes): array => [
                'tenant_id' => $attributes['tenant_id'],
            ]),
            'extension_id' => Extension::factory()->state(fn (array $attributes): array => [
                'tenant_id' => $attributes['tenant_id'],
            ]),
            'registration_key' => fake()->bothify('reg-########'),
            'registered' => true,
            'user_agent' => fake()->userAgent(),
            'network_ip' => fake()->ipv4(),
            'observed_at' => now(),
        ];
    }
}
