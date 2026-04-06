<?php

namespace App\Services\Call;

use App\Models\CallEventLog;

class CallEventProcessor
{
    public function __construct(
        protected TraceWriter $traceWriter,
        protected CallLockService $callLockService,
    ) {}

    public function process(CallEventLog $event): ?array
    {
        $session = $event->callSession;

        if (! $session) {
            return null;
        }

        return $this->callLockService->withLock($session->call_uuid, function () use ($event, $session) {
            // Reload session to ensure we have the latest state and lock version
            $session->refresh();

            // Idempotency check: if the event was already processed, skip
            if ($session->events()->where('id', $event->id)->where('status', 'processed')->exists() || isset($event->payload['processed_at'])) {
                // Actually, event status isn't built yet, so we'll just check if it's already marked in variables
                $processedEvents = $session->variables['processed_events'] ?? [];
                if (in_array($event->id, $processedEvents, true)) {
                    return null;
                }
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

            $processedEvents = $variables['processed_events'] ?? [];
            $processedEvents[] = $event->id;
            $variables['processed_events'] = $processedEvents;

            $session->forceFill([
                'variables' => $variables,
                'state' => 'processing',
                'lock_version' => $session->lock_version + 1,
            ])->save();

            $this->traceWriter->write($session, 'call.event.processed', [
                'event_type' => $event->event_type,
                'event_id' => $event->event_id,
            ]);

            return null;
        });
    }
}
