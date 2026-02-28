<?php

namespace Modules\PbxProvisioning;

use App\Modules\BaseModule;

class PbxProvisioningModule extends BaseModule
{
    public function name(): string
    {
        return 'pbx-provisioning';
    }

    public function description(): string
    {
        return 'Provisioning: device templates, MAC detection, credential injection';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function subscribedEvents(): array
    {
        return [
            'device.registered',
            'device.unregistered',
        ];
    }

    public function permissions(): array
    {
        return [
            'device-profiles.view',
            'device-profiles.manage',
        ];
    }

    public function routesFile(): ?string
    {
        return __DIR__.'/../routes/api.php';
    }
}
