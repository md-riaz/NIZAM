<?php

namespace App\Observers;

use App\Models\CallRoutingPolicy;

class CallRoutingPolicyObserver
{
    use RebuildsTenantManifest;

    public function created(CallRoutingPolicy $policy): void
    {
        $this->rebuildTenantManifestForModel($policy);
    }

    public function updated(CallRoutingPolicy $policy): void
    {
        $this->rebuildTenantManifestForModel($policy);
    }

    public function deleted(CallRoutingPolicy $policy): void
    {
        $this->rebuildTenantManifestForModel($policy);
    }
}
