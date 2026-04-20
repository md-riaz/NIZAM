<?php

namespace App\Observers;

use App\Models\Ivr;

class IvrObserver
{
    use RebuildsOrganizationManifest;

    public function created(Ivr $ivr): void
    {
        $this->rebuildOrganizationManifestForModel($ivr);
    }

    public function updated(Ivr $ivr): void
    {
        $this->rebuildOrganizationManifestForModel($ivr);
    }

    public function deleted(Ivr $ivr): void
    {
        $this->rebuildOrganizationManifestForModel($ivr);
    }
}
