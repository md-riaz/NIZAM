<?php

namespace App\Observers;

use App\Models\ScheduleException;

class ScheduleExceptionObserver
{
    use RebuildsOrganizationManifest;

    public function created(ScheduleException $scheduleException): void
    {
        $this->rebuildOrganizationManifestForModel($scheduleException);
    }

    public function updated(ScheduleException $scheduleException): void
    {
        $this->rebuildOrganizationManifestForModel($scheduleException);
    }

    public function deleted(ScheduleException $scheduleException): void
    {
        $this->rebuildOrganizationManifestForModel($scheduleException);
    }
}
