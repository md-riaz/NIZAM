<?php

namespace App\Observers;

use App\Models\RingGroup;

class RingGroupObserver
{
    use RebuildsOrganizationManifest;

    public function created(RingGroup $ringGroup): void
    {
        $this->rebuildOrganizationManifestForModel($ringGroup);
    }

    public function updated(RingGroup $ringGroup): void
    {
        $this->rebuildOrganizationManifestForModel($ringGroup);
    }

    public function deleted(RingGroup $ringGroup): void
    {
        $this->rebuildOrganizationManifestForModel($ringGroup);
    }
}
