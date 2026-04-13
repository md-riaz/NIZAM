<?php

namespace App\Observers;

use App\Models\RingGroup;

class RingGroupObserver
{
    use RebuildsTenantManifest;

    public function created(RingGroup $ringGroup): void
    {
        $this->rebuildTenantManifestForModel($ringGroup);
    }

    public function updated(RingGroup $ringGroup): void
    {
        $this->rebuildTenantManifestForModel($ringGroup);
    }

    public function deleted(RingGroup $ringGroup): void
    {
        $this->rebuildTenantManifestForModel($ringGroup);
    }
}
