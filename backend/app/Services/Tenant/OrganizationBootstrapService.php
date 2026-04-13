<?php

namespace App\Services\Tenant;

use App\Models\HolidayCalendar;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Tenant;

class OrganizationBootstrapService
{
    public function provisionDefaults(Tenant $tenant): Tenant
    {
        $settings = $tenant->settings ?? [];
        $settings['timezone'] ??= (string) config('telephony.bootstrap.default_timezone', 'Asia/Dhaka');
        $settings['country'] ??= (string) config('telephony.bootstrap.default_country', 'Bangladesh');
        $settings['default_country_code'] ??= '880';

        $holidayCalendar = $tenant->defaultHolidayCalendar;
        if (! $holidayCalendar) {
            $holidayCalendar = $tenant->holidayCalendars()->create([
                'name' => 'Bangladesh Holidays',
                'timezone' => $settings['timezone'],
                'is_active' => true,
            ]);
        }

        $schedule = $tenant->defaultSchedule;
        if (! $schedule) {
            $schedule = $tenant->schedules()->create([
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

        $tenant->forceFill([
            'default_schedule_id' => $schedule->id,
            'default_holiday_calendar_id' => $holidayCalendar->id,
            'settings' => $settings,
        ])->save();

        return $tenant->fresh(['defaultSchedule', 'defaultHolidayCalendar']);
    }
}
