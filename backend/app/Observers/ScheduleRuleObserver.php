<?php

namespace App\Observers;

use App\Models\ScheduleRule;

class ScheduleRuleObserver
{
    use RebuildsOrganizationManifest;

    public function created(ScheduleRule $scheduleRule): void
    {
        $this->rebuildOrganizationManifestForModel($scheduleRule);
    }

    public function updated(ScheduleRule $scheduleRule): void
    {
        $this->rebuildOrganizationManifestForModel($scheduleRule);
    }

    public function deleted(ScheduleRule $scheduleRule): void
    {
        $this->rebuildOrganizationManifestForModel($scheduleRule);
    }
}
