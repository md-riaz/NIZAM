<?php

namespace App\Services\Call;

use App\Models\CallEventLog;
use App\Models\FlowVersion;
use App\Services\Flow\FlowRuntimeStarter;

class CallEventProcessor
{
    public function __construct(
        protected FlowRuntimeStarter $flowRuntimeStarter,
        protected TraceWriter $traceWriter,
    ) {}

    public function process(CallEventLog $event): ?array
    {
        $session = $event->callSession;

        if (! $session || ! $session->flow_version_id) {
            return null;
        }

        $variables = $session->variables ?? [];

        if ($event->event_type === 'menu.selection') {
            $variables['menu_digit'] = (string) data_get($event->payload, 'digit');
        }

        if ($event->event_type === 'call.answered') {
            $variables['team_answered'] = true;
        }

        if ($event->event_type === 'call.timeout') {
            $variables['team_answered'] = false;
        }

        $session->forceFill([
            'variables' => $variables,
            'state' => 'resuming',
            'lock_version' => $session->lock_version + 1,
        ])->save();

        $this->traceWriter->write($session, 'call.event.processed', [
            'event_type' => $event->event_type,
            'event_id' => $event->event_id,
        ]);

        $flowVersion = FlowVersion::find($session->flow_version_id);

        if (! $flowVersion) {
            return null;
        }

        return $this->flowRuntimeStarter->start($session, $flowVersion);
    }
}
