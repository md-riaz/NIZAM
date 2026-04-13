<?php

namespace App\Observers;

use App\Models\TimeCondition;

class TimeConditionObserver
{
    use RebuildsTenantManifest;

    public function created(TimeCondition $timeCondition): void
    {
        $this->rebuildTenantManifestForModel($timeCondition);
    }

    public function updated(TimeCondition $timeCondition): void
    {
        $this->rebuildTenantManifestForModel($timeCondition);
    }

    public function deleted(TimeCondition $timeCondition): void
    {
        $this->rebuildTenantManifestForModel($timeCondition);
    }
}
