<?php

namespace App\Observers;

use App\Models\Ivr;

class IvrObserver
{
    use RebuildsTenantManifest;

    public function created(Ivr $ivr): void
    {
        $this->rebuildTenantManifestForModel($ivr);
    }

    public function updated(Ivr $ivr): void
    {
        $this->rebuildTenantManifestForModel($ivr);
    }

    public function deleted(Ivr $ivr): void
    {
        $this->rebuildTenantManifestForModel($ivr);
    }
}
