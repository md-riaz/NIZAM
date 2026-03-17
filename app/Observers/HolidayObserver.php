<?php

namespace App\Observers;

use App\Models\Holiday;

class HolidayObserver
{
    use RebuildsTenantManifest;

    public function created(Holiday $holiday): void
    {
        $this->rebuildTenantManifestForModel($holiday);
    }

    public function updated(Holiday $holiday): void
    {
        $this->rebuildTenantManifestForModel($holiday);
    }

    public function deleted(Holiday $holiday): void
    {
        $this->rebuildTenantManifestForModel($holiday);
    }
}
