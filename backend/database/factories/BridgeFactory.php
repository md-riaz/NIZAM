<?php

namespace Database\Factories;

use App\Models\Bridge;
use App\Models\Gateway;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BridgeFactory extends Factory
{
    protected $model = Bridge::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true).' bridge',
            'bridge_type' => 'gateway',
            'gateway_id' => Gateway::factory(),
            'destination_template' => '+15551234567',
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
