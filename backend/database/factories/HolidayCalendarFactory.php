<?php

namespace Database\Factories;

use App\Models\HolidayCalendar;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayCalendarFactory extends Factory
{
    protected $model = HolidayCalendar::class;

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
