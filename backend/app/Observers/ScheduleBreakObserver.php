<?php

namespace App\Observers;

use App\Models\ScheduleBreak;

class ScheduleBreakObserver
{
    use RebuildsTenantManifest;

    public function created(ScheduleBreak $scheduleBreak): void
    {
        $this->rebuildTenantManifestForModel($scheduleBreak);
    }

    public function updated(ScheduleBreak $scheduleBreak): void
    {
        $this->rebuildTenantManifestForModel($scheduleBreak);
    }

    public function deleted(ScheduleBreak $scheduleBreak): void
    {
        $this->rebuildTenantManifestForModel($scheduleBreak);
    }
}
