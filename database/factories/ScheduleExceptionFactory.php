<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleExceptionFactory extends Factory
{
    protected $model = ScheduleException::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'start_datetime' => now(),
            'end_datetime' => now()->addHour(),
            'state' => 'open',
        ];
    }
}
