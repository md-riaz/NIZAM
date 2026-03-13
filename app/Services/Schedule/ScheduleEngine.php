<?php

namespace App\Services\Schedule;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class ScheduleEngine
{
    public function evaluate(Schedule $schedule, DateTimeInterface|string|null $at = null): string
    {
        $moment = $at instanceof DateTimeInterface
            ? CarbonImmutable::instance((new \DateTimeImmutable($at->format(DATE_ATOM))))
            : CarbonImmutable::parse($at ?? 'now', $schedule->timezone);

        $moment = $moment->setTimezone($schedule->timezone);

        if ($this->isHoliday($schedule, $moment)) {
            return 'holiday';
        }

        $exception = $schedule->exceptions()
            ->where('start_datetime', '<=', $moment)
            ->where('end_datetime', '>=', $moment)
            ->orderBy('start_datetime')
            ->first();

        if ($exception) {
            return (string) $exception->state;
        }

        $dayOfWeek = (int) $moment->dayOfWeek;
        $time = $moment->format('H:i:s');

        $hasBreak = $schedule->breaks()
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $time)
            ->exists();

        if ($hasBreak) {
            return 'break';
        }

        $isOpen = $schedule->rules()
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $time)
            ->exists();

        return $isOpen ? 'open' : 'closed';
    }

    protected function isHoliday(Schedule $schedule, CarbonImmutable $moment): bool
    {
        $calendar = $schedule->holidayCalendar;

        if (! $calendar || ! $calendar->is_active) {
            return false;
        }

        return $calendar->holidays()
            ->where('holiday_date', $moment->toDateString())
            ->where('is_active', true)
            ->exists();
    }
}
