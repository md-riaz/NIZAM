<?php

namespace App\Observers;

use App\Models\Did;

class DidObserver
{
    use RebuildsOrganizationManifest;

    public function created(Did $did): void
    {
        $this->rebuildOrganizationManifestForModel($did);
    }

    public function updated(Did $did): void
    {
        $this->rebuildOrganizationManifestForModel($did);
    }

    public function deleted(Did $did): void
    {
        $this->rebuildOrganizationManifestForModel($did);
    }
}
