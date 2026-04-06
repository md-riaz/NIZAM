<?php

namespace Database\Factories;

use App\Models\Holiday;
use App\Models\HolidayCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'holiday_calendar_id' => HolidayCalendar::factory(),
            'name' => $this->faker->word,
            'holiday_date' => $this->faker->date(),
            'is_active' => true,
        ];
    }
}
