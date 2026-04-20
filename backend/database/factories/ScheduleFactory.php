<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->word,
            'timezone' => 'UTC',
            'is_active' => true,
        ];
    }
}
