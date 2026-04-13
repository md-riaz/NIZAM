<?php

namespace App\Observers;

use App\Models\Did;

class DidObserver
{
    use RebuildsTenantManifest;

    public function created(Did $did): void
    {
        $this->rebuildTenantManifestForModel($did);
    }

    public function updated(Did $did): void
    {
        $this->rebuildTenantManifestForModel($did);
    }

    public function deleted(Did $did): void
    {
        $this->rebuildTenantManifestForModel($did);
    }
}
