<?php

namespace App\Services\Organization;

use App\Models\HolidayCalendar;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Organization;

class OrganizationBootstrapService
{
    public function provisionDefaults(Organization $organization): Organization
    {
        $settings = $organization->settings ?? [];
        $settings['timezone'] ??= (string) config('telephony.bootstrap.default_timezone', 'Asia/Dhaka');
        $settings['country'] ??= (string) config('telephony.bootstrap.default_country', 'Bangladesh');
        $settings['default_country_code'] ??= '880';

        $holidayCalendar = $organization->defaultHolidayCalendar;
        if (! $holidayCalendar) {
            $holidayCalendar = $organization->holidayCalendars()->create([
                'name' => 'Bangladesh Holidays',
                'timezone' => $settings['timezone'],
                'is_active' => true,
            ]);
        }

        $schedule = $organization->defaultSchedule;
        if (! $schedule) {
            $schedule = $organization->schedules()->create([
                'holiday_calendar_id' => $holidayCalendar->id,
                'name' => 'Main Business Hours',
                'timezone' => $settings['timezone'],
                'is_active' => true,
            ]);

            foreach ((array) config('telephony.bootstrap.business_hours.days', [1, 2, 3, 4, 5]) as $dayOfWeek) {
                ScheduleRule::create([
                    'schedule_id' => $schedule->id,
                    'day_of_week' => (int) $dayOfWeek,
                    'start_time' => (string) config('telephony.bootstrap.business_hours.start', '09:00'),
                    'end_time' => (string) config('telephony.bootstrap.business_hours.end', '17:00'),
                ]);
            }
        }

        $organization->forceFill([
            'default_schedule_id' => $schedule->id,
            'default_holiday_calendar_id' => $holidayCalendar->id,
            'settings' => $settings,
        ])->save();

        return $organization->fresh(['defaultSchedule', 'defaultHolidayCalendar']);
    }
}
