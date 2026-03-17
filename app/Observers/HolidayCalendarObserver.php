<?php

namespace App\Observers;

use App\Models\HolidayCalendar;

class HolidayCalendarObserver
{
    use RebuildsTenantManifest;

    public function created(HolidayCalendar $holidayCalendar): void
    {
        $this->rebuildTenantManifestForModel($holidayCalendar);
    }

    public function updated(HolidayCalendar $holidayCalendar): void
    {
        $this->rebuildTenantManifestForModel($holidayCalendar);
    }

    public function deleted(HolidayCalendar $holidayCalendar): void
    {
        $this->rebuildTenantManifestForModel($holidayCalendar);
    }
}
