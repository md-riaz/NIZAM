<?php

namespace App\Observers;

use App\Models\ScheduleRule;

class ScheduleRuleObserver
{
    use RebuildsTenantManifest;

    public function created(ScheduleRule $scheduleRule): void
    {
        $this->rebuildTenantManifestForModel($scheduleRule);
    }

    public function updated(ScheduleRule $scheduleRule): void
    {
        $this->rebuildTenantManifestForModel($scheduleRule);
    }

    public function deleted(ScheduleRule $scheduleRule): void
    {
        $this->rebuildTenantManifestForModel($scheduleRule);
    }
}
