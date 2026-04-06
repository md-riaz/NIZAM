<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleBreakFactory extends Factory
{
    protected $model = ScheduleBreak::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'start_time' => '12:00',
            'end_time' => '13:00',
        ];
    }
}
