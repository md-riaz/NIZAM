<?php

namespace App\Services\Schedule\Compile;

use App\Models\Schedule;

/**
 * Compiles a Schedule into FreeSWITCH dialplan time conditions.
 *
 * Instead of runtime PHP evaluation, this generates XML dialplan
 * conditions that FreeSWITCH can evaluate locally on every call.
 *
 * Precedence: holiday → exception → break → open → closed
 */
class ScheduleCompiler
{
    /**
     * Compile a schedule into dialplan XML fragment.
     */
    public function compile(Schedule $schedule): string
    {
        $schedule->load(['rules', 'breaks', 'exceptions', 'holidayCalendar.holidays']);

        $xml = [];
        $xml[] = '<extension name="schedule_'.$schedule->id.'">';

        // Holiday check (highest precedence)
        $holidayXml = $this->compileHolidayCheck($schedule);
        if ($holidayXml) {
            $xml[] = $holidayXml;
        }

        // Exception check
        $exceptionXml = $this->compileExceptionCheck($schedule);
        if ($exceptionXml) {
            $xml[] = $exceptionXml;
        }

        // Break check
        $breakXml = $this->compileBreakCheck($schedule);
        if ($breakXml) {
            $xml[] = $breakXml;
        }

        // Regular hours check
        $hoursXml = $this->compileRegularHours($schedule);
        if ($hoursXml) {
            $xml[] = $hoursXml;
        }

        // Default to closed
        $xml[] = '    <action application="set" data="nizam_schedule_state=closed"/>';
        $xml[] = '    <action application="transfer" data="schedule_'.$schedule->id.'_closed XML default"/>';

        $xml[] = '</extension>';

        return implode("\n", $xml);
    }

    /**
     * Compile holiday check conditions.
     */
    protected function compileHolidayCheck(Schedule $schedule): ?string
    {
        $holidays = $schedule->holidayCalendar?->holidays ?? [];

        if (empty($holidays)) {
            return null;
        }

        $xml = [];
        $xml[] = '    <condition field="destination_number" expression=".*">';

        // Build date regex for holidays (YYYY-MM-DD format)
        $holidayDates = array_map(fn($h) => date('Y-m-d', strtotime($h->date)), $holidays);
        $dateRegex = '^('.implode('|', $holidayDates).')$';

        $xml[] = '        <condition field="strftime(%Y-%m-%d)" expression="'.$dateRegex.'">';
        $xml[] = '            <action application="set" data="nizam_schedule_state=holiday"/>';
        $xml[] = '            <action application="transfer" data="schedule_'.$schedule->id.'_holiday XML default"/>';
        $xml[] = '        </condition>';

        $xml[] = '    </condition>';

        return implode("\n", $xml);
    }

    /**
     * Compile exception check conditions.
     */
    protected function compileExceptionCheck(Schedule $schedule): ?string
    {
        $exceptions = $schedule->exceptions ?? [];

        if (empty($exceptions)) {
            return null;
        }

        $xml = [];
        $xml[] = '    <condition field="destination_number" expression=".*">';

        foreach ($exceptions as $exception) {
            $date = date('Y-m-d', strtotime($exception->date));
            $state = $exception->state; // open, closed, break

            $xml[] = '        <condition field="strftime(%Y-%m-%d)" expression="^'.$date.'$">';
            $xml[] = '            <action application="set" data="nizam_schedule_state='.$state.'"/>';
            $xml[] = '            <action application="transfer" data="schedule_'.$schedule->id.'_exception_'.$state.' XML default"/>';
            $xml[] = '        </condition>';
        }

        $xml[] = '    </condition>';

        return implode("\n", $xml);
    }

    /**
     * Compile break time check conditions.
     */
    protected function compileBreakCheck(Schedule $schedule): ?string
    {
        $breaks = $schedule->breaks ?? [];

        if (empty($breaks)) {
            return null;
        }

        $xml = [];
        $xml[] = '    <condition field="destination_number" expression=".*">';

        // Build time ranges for breaks
        $timeConditions = [];
        foreach ($breaks as $break) {
            $start = $break->start_time; // HH:MM format
            $end = $break->end_time;
            $wday = $break->day_of_week; // 0-6

            $timeConditions[] = [
                'wday' => $wday,
                'start' => $start,
                'end' => $end,
            ];
        }

        foreach ($timeConditions as $tc) {
            $xml[] = '        <condition field="wday" expression="^'.$tc['wday'].'$">';
            $xml[] = '            <condition field="strftime(%H:%M)" expression="^'.$tc['start'].'-'.$tc['end'].'$">';
            $xml[] = '                <action application="set" data="nizam_schedule_state=break"/>';
            $xml[] = '                <action application="transfer" data="schedule_'.$schedule->id.'_break XML default"/>';
            $xml[] = '            </condition>';
            $xml[] = '        </condition>';
        }

        $xml[] = '    </condition>';

        return implode("\n", $xml);
    }

    /**
     * Compile regular business hours check.
     */
    protected function compileRegularHours(Schedule $schedule): ?string
    {
        $rules = $schedule->rules ?? [];

        if (empty($rules)) {
            return null;
        }

        $xml = [];
        $xml[] = '    <condition field="destination_number" expression=".*">';

        foreach ($rules as $rule) {
            $wday = $rule->day_of_week;
            $start = $rule->start_time;
            $end = $rule->end_time;

            $xml[] = '        <condition field="wday" expression="^'.$wday.'$">';
            $xml[] = '            <condition field="strftime(%H:%M)" expression="^'.$start.'-'.$end.'$">';
            $xml[] = '                <action application="set" data="nizam_schedule_state=open"/>';
            $xml[] = '                <action application="transfer" data="schedule_'.$schedule->id.'_open XML default"/>';
            $xml[] = '            </condition>';
            $xml[] = '        </condition>';
        }

        $xml[] = '    </condition>';

        return implode("\n", $xml);
    }
}
