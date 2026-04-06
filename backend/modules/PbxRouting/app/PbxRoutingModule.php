<?php

namespace Modules\PbxRouting;

use App\Modules\BaseModule;

class PbxRoutingModule extends BaseModule
{
    public function name(): string
    {
        return 'pbx-routing';
    }

    public function description(): string
    {
        return 'PBX routing: DIDs, ring groups, IVR, time conditions';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function subscribedEvents(): array
    {
        return [
            'call.created',
            'call.answered',
            'call.hangup',
        ];
    }

    public function permissions(): array
    {
        return [
            'dids.view',
            'dids.manage',
            'ring-groups.view',
            'ring-groups.manage',
            'ivrs.view',
            'ivrs.manage',
            'time-conditions.view',
            'time-conditions.manage',
        ];
    }

    public function routesFile(): ?string
    {
        return __DIR__.'/../routes/api.php';
    }
}
