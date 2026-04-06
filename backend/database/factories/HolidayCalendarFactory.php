<?php

namespace Database\Factories;

use App\Models\HolidayCalendar;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayCalendarFactory extends Factory
{
    protected $model = HolidayCalendar::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->word,
            'timezone' => 'UTC',
            'is_active' => true,
        ];
    }
}
