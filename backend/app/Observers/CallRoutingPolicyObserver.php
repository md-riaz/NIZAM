<?php

namespace App\Observers;

use App\Models\CallRoutingPolicy;

class CallRoutingPolicyObserver
{
    use RebuildsOrganizationManifest;

    public function created(CallRoutingPolicy $policy): void
    {
        $this->rebuildOrganizationManifestForModel($policy);
    }

    public function updated(CallRoutingPolicy $policy): void
    {
        $this->rebuildOrganizationManifestForModel($policy);
    }

    public function deleted(CallRoutingPolicy $policy): void
    {
        $this->rebuildOrganizationManifestForModel($policy);
    }
}
