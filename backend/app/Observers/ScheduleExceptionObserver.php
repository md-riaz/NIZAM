<?php

namespace App\Observers;

use App\Models\ScheduleException;

class ScheduleExceptionObserver
{
    use RebuildsTenantManifest;

    public function created(ScheduleException $scheduleException): void
    {
        $this->rebuildTenantManifestForModel($scheduleException);
    }

    public function updated(ScheduleException $scheduleException): void
    {
        $this->rebuildTenantManifestForModel($scheduleException);
    }

    public function deleted(ScheduleException $scheduleException): void
    {
        $this->rebuildTenantManifestForModel($scheduleException);
    }
}
