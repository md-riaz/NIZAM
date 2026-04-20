<?php

namespace App\Observers;

use App\Models\TimeCondition;

class TimeConditionObserver
{
    use RebuildsOrganizationManifest;

    public function created(TimeCondition $timeCondition): void
    {
        $this->rebuildOrganizationManifestForModel($timeCondition);
    }

    public function updated(TimeCondition $timeCondition): void
    {
        $this->rebuildOrganizationManifestForModel($timeCondition);
    }

    public function deleted(TimeCondition $timeCondition): void
    {
        $this->rebuildOrganizationManifestForModel($timeCondition);
    }
}
