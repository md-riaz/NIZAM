<?php

namespace Database\Factories;

use App\Models\Gateway;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gateway>
 */
class GatewayFactory extends Factory
{
    protected $model = Gateway::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company().' SIP Trunk',
            'host' => fake()->ipv4(),
            'port' => 5060,
            'username' => fake()->userName(),
            'password' => fake()->password(),
            'realm' => fake()->domainName(),
            'transport' => fake()->randomElement(['udp', 'tcp', 'tls']),
            'inbound_codecs' => ['PCMU', 'PCMA', 'G722'],
            'outbound_codecs' => ['PCMU', 'PCMA'],
            'allow_transcoding' => true,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
