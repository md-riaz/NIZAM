<?php

namespace App\Modules;

class PbxContactCenterModule extends BaseModule
{
    public function name(): string
    {
        return 'pbx-contact-center';
    }

    public function description(): string
    {
        return 'Contact center: queue engine, agent state, SLA metrics, wallboard';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function subscribedEvents(): array
    {
        return [
            'queue.call_joined',
            'queue.call_answered',
            'queue.call_abandoned',
            'queue.call_overflowed',
            'agent.state_changed',
        ];
    }

    public function permissions(): array
    {
        return [
            'agents.view',
            'agents.manage',
            'queues.view',
            'queues.manage',
            'queues.metrics',
            'wallboard.view',
        ];
    }

    public function routesFile(): ?string
    {
        return base_path('routes/modules/pbx-contact-center.php');
    }
}
