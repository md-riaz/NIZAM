<?php

namespace App\Modules;

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
        return base_path('routes/modules/pbx-provisioning.php');
    }
}
