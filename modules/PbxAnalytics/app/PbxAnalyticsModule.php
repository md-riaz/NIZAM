<?php

namespace Modules\PbxAnalytics;

use App\Modules\BaseModule;

class PbxAnalyticsModule extends BaseModule
{
    public function name(): string
    {
        return 'pbx-analytics';
    }

    public function description(): string
    {
        return 'Analytics: insights, anomaly detection, recording enrichment';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function subscribedEvents(): array
    {
        return [
            'call.hangup',
            'recording.completed',
        ];
    }

    public function permissions(): array
    {
        return [
            'analytics.view',
            'recordings.view',
            'recordings.download',
            'recordings.delete',
        ];
    }

    public function routesFile(): ?string
    {
        return __DIR__.'/../routes/api.php';
    }
}
