<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleRuleFactory extends Factory
{
    protected $model = ScheduleRule::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ];
    }
}
