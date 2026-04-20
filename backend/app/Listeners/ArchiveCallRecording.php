<?php

namespace App\Listeners;

use App\Events\CallDetailRecordCreated;
use App\Modules\ModuleRegistry;

class ArchiveCallRecording
{
    public function __construct(
        protected ModuleRegistry $moduleRegistry,
    ) {}

    public function handle(CallDetailRecordCreated $event): void
    {
        $cdr = $event->cdr;

        $payload = [
            'organization_id' => $cdr->organization_id,
            'call_uuid' => $cdr->uuid,
            'recording_path' => $cdr->recording_path,
            'direction' => $cdr->direction,
            'caller_id_number' => $cdr->caller_id_number,
            'destination_number' => $cdr->destination_number,
            'duration' => $cdr->duration,
            'billsec' => $cdr->billsec,
            'hangup_cause' => $cdr->hangup_cause,
            'end_stamp' => optional($cdr->end_stamp)?->toIso8601String(),
            'metadata' => $cdr->metadata ?? [],
        ];

        $this->moduleRegistry->dispatchEvent('call.end', $payload);
    }
}
