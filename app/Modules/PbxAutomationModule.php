<?php

namespace App\Modules;

class PbxAutomationModule extends BaseModule
{
    public function name(): string
    {
        return 'pbx-automation';
    }

    public function description(): string
    {
        return 'Automation: webhooks, event subscriptions, call control API';
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
            'call.bridged',
        ];
    }

    public function permissions(): array
    {
        return [
            'webhooks.view',
            'webhooks.manage',
            'calls.originate',
            'calls.control',
            'call-events.view',
            'call-events.redispatch',
        ];
    }

    public function routesFile(): ?string
    {
        return base_path('routes/modules/pbx-automation.php');
    }
}
