<?php

namespace App\Observers;

use App\Models\Schedule;

class ScheduleObserver
{
    use RebuildsTenantManifest;

    public function created(Schedule $schedule): void
    {
        $this->rebuildTenantManifestForModel($schedule);
    }

    public function updated(Schedule $schedule): void
    {
        $this->rebuildTenantManifestForModel($schedule);
    }

    public function deleted(Schedule $schedule): void
    {
        $this->rebuildTenantManifestForModel($schedule);
    }
}
