<?php

namespace App\Services\Call;

use App\Jobs\ProcessCallEventJob;
use App\Models\CallEventLog;
use App\Models\CallSession;
use App\Models\Organization;
use Illuminate\Support\Str;

class CallEventIngestionService
{
    public function ingest(
        Organization $organization,
        string $eventType,
        string $callUuid,
        array $payload,
        ?CallSession $callSession = null,
        string $source = 'freeswitch'
    ): CallEventLog {
        $eventId = (string) ($payload['event_id'] ?? Str::uuid());

        $event = CallEventLog::firstOrCreate(
            [
                'call_uuid' => $callUuid,
                'event_id' => $eventId,
            ],
            [
                'call_session_id' => $callSession?->id,
                'organization_id' => $organization->id,
                'event_type' => $eventType,
                'source' => $source,
                'payload' => $payload,
                'occurred_at' => now(),
                'received_at' => now(),
            ]
        );

        if ($event->wasRecentlyCreated && in_array($eventType, [
            'menu.selection',
            CallEventLog::EVENT_CALL_ANSWERED,
            'call.timeout',
        ], true)) {
            ProcessCallEventJob::dispatch($event->id);
        }

        return $event;
    }
}
