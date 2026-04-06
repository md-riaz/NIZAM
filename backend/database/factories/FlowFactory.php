<?php

namespace Database\Factories;

use App\Models\Flow;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class FlowFactory extends Factory
{
    protected $model = Flow::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'active_version_id' => null,
        ];
    }
}
