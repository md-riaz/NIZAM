<?php

namespace App\Observers;

use App\Models\ScheduleBreak;

class ScheduleBreakObserver
{
    use RebuildsOrganizationManifest;

    public function created(ScheduleBreak $scheduleBreak): void
    {
        $this->rebuildOrganizationManifestForModel($scheduleBreak);
    }

    public function updated(ScheduleBreak $scheduleBreak): void
    {
        $this->rebuildOrganizationManifestForModel($scheduleBreak);
    }

    public function deleted(ScheduleBreak $scheduleBreak): void
    {
        $this->rebuildOrganizationManifestForModel($scheduleBreak);
    }
}
