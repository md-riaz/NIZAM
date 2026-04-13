<?php

namespace App\Services\Policy;

use App\Models\HolidayCalendar;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Schedule\ScheduleEngine;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class SchedulePolicyEngine
{
    public function __construct(
        protected ScheduleEngine $scheduleEngine,
    ) {}

    /**
     * @param  Tenant|Team|User  $target
     * @return array{
     *     open: bool,
     *     state: string,
     *     schedule: ?Schedule,
     *     holiday_calendar: ?HolidayCalendar,
     *     inherited_from: string,
     *     evaluated_at: CarbonImmutable
     * }
     */
    public function evaluate(Tenant|Team|User $target, DateTimeInterface|string|null $at = null): array
    {
        $context = $this->resolveContext($target);
        $evaluatedAt = $this->resolveMoment($at, $context['timezone']);
        $state = $this->resolveState($context['schedule'], $context['holiday_calendar'], $evaluatedAt);

        return [
            'open' => $state === 'open',
            'state' => $state,
            'schedule' => $context['schedule'],
            'holiday_calendar' => $context['holiday_calendar'],
            'inherited_from' => $context['inherited_from'],
            'evaluated_at' => $evaluatedAt,
        ];
    }

    public function isOpenNow(Tenant|Team|User $target, DateTimeInterface|string|null $at = null): bool
    {
        return $this->evaluate($target, $at)['open'];
    }

    /**
     * @param  Tenant|Team|User  $target
     * @return array{schedule: ?Schedule, holiday_calendar: ?HolidayCalendar, inherited_from: string, timezone: string}
     */
    public function resolveContext(Tenant|Team|User $target): array
    {
        if ($target instanceof Tenant) {
            $schedule = $this->resolveScheduleById($target->default_schedule_id);
            $holidayCalendar = $this->resolveHolidayCalendarById($target->default_holiday_calendar_id)
                ?: $schedule?->holidayCalendar;

            return [
                'schedule' => $schedule,
                'holiday_calendar' => $holidayCalendar,
                'inherited_from' => 'organization',
                'timezone' => $schedule?->timezone ?? $holidayCalendar?->timezone ?? 'UTC',
            ];
        }

        if ($target instanceof Team) {
            $schedule = $target->effectiveSchedule();
            $holidayCalendar = $target->effectiveHolidayCalendar();

            return [
                'schedule' => $schedule,
                'holiday_calendar' => $holidayCalendar,
                'inherited_from' => ($target->schedule_id || $target->holiday_calendar_id)
                    ? 'team'
                    : 'organization',
                'timezone' => $schedule?->timezone ?? $holidayCalendar?->timezone ?? 'UTC',
            ];
        }

        if ($target instanceof User) {
            $schedule = $target->effectiveSchedule();
            $holidayCalendar = $target->effectiveHolidayCalendar();

            return [
                'schedule' => $schedule,
                'holiday_calendar' => $holidayCalendar,
                'inherited_from' => ($target->schedule_id || $target->holiday_calendar_id)
                    ? 'user'
                    : 'organization',
                'timezone' => $schedule?->timezone ?? $holidayCalendar?->timezone ?? 'UTC',
            ];
        }

        throw new InvalidArgumentException('Unsupported schedule policy target.');
    }

    protected function resolveScheduleById(?string $scheduleId): ?Schedule
    {
        if (! $scheduleId) {
            return null;
        }

        return Schedule::query()->find($scheduleId);
    }

    protected function resolveHolidayCalendarById(?string $holidayCalendarId): ?HolidayCalendar
    {
        if (! $holidayCalendarId) {
            return null;
        }

        return HolidayCalendar::query()->find($holidayCalendarId);
    }

    protected function resolveState(?Schedule $schedule, ?HolidayCalendar $holidayCalendar, DateTimeInterface|string|null $at): string
    {
        if (! $schedule || ! $schedule->is_active) {
            return 'closed';
        }

        $moment = $this->resolveMoment($at, $schedule->timezone);

        if ($holidayCalendar && $holidayCalendar->is_active) {
            if ($this->isHoliday($holidayCalendar, $moment)) {
                return 'holiday';
            }

            if ($schedule->holiday_calendar_id !== $holidayCalendar->id) {
                $scheduleId = $schedule->getKey();
                $schedule = $schedule->replicate();
                $schedule->setAttribute($schedule->getKeyName(), $scheduleId);
                $schedule->setAttribute('holiday_calendar_id', $holidayCalendar->id);
                $schedule->exists = true;
                $schedule->setRelation('holidayCalendar', $holidayCalendar);
            }
        }

        return $this->scheduleEngine->evaluate($schedule, $moment);
    }

    protected function isHoliday(HolidayCalendar $holidayCalendar, CarbonImmutable $moment): bool
    {
        return $holidayCalendar->holidays()
            ->whereDate('holiday_date', $moment->toDateString())
            ->where('is_active', true)
            ->exists();
    }

    protected function resolveMoment(DateTimeInterface|string|null $at, string $timezone): CarbonImmutable
    {
        if ($at instanceof DateTimeInterface) {
            return CarbonImmutable::instance((new \DateTimeImmutable($at->format(DATE_ATOM))))
                ->setTimezone($timezone);
        }

        return CarbonImmutable::parse($at ?? 'now', $timezone)->setTimezone($timezone);
    }
}
