<?php

namespace App\Services\SupervisorReports;

use App\Models\CallEventLog;
use App\Models\Recording;
use App\Models\Organization;
use Carbon\CarbonInterface;

class VoicemailsNeedingFollowUpReportService
{
    public function __construct(
        protected ReturnedCallResolver $returnedCallResolver,
    ) {}

    public function generate(Organization $organization, CarbonInterface $from, CarbonInterface $to, ?int $windowDays = null): array
    {
        $windowDays ??= $this->returnedCallResolver->defaultWindowDays();

        $voicemailEvents = CallEventLog::query()
            ->where('organization_id', $organization->id)
            ->where('event_type', CallEventLog::EVENT_VOICEMAIL_RECEIVED)
            ->whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('occurred_at')
            ->get();

        $items = $voicemailEvents->map(function (CallEventLog $event) use ($organization, $windowDays): array {
            $callerNumber = (string) data_get($event->payload, 'metadata.caller_id_number', '');
            $normalizedCallerNumber = $this->returnedCallResolver->normalizeNumber($callerNumber);
            $returnedCall = $this->returnedCallResolver->findReturnedCall(
                $organization,
                $normalizedCallerNumber,
                $event->occurred_at,
                $windowDays,
            );

            $recording = $this->resolveVoicemailRecording($organization, $event);
            $needsAttention = ($recording?->needs_review ?? false) || $returnedCall === null;

            return [
                'event_id' => $event->id,
                'call_uuid' => $event->call_uuid,
                'caller_id_number' => $callerNumber,
                'normalized_caller_number' => $normalizedCallerNumber,
                'mailbox' => data_get($event->payload, 'metadata.user'),
                'received_at' => $event->occurred_at?->toIso8601String(),
                'follow_up_status' => $returnedCall ? 'returned' : 'pending',
                'needs_attention' => $needsAttention,
                'recording' => $recording ? [
                    'id' => $recording->id,
                    'call_uuid' => $recording->call_uuid,
                    'needs_review' => (bool) $recording->needs_review,
                    'review_reasons' => $recording->review_reasons,
                    'file_name' => $recording->file_name,
                ] : null,
                'returned_call' => $returnedCall ? [
                    'cdr_id' => $returnedCall->id,
                    'call_uuid' => $returnedCall->uuid,
                    'started_at' => $returnedCall->start_stamp?->toIso8601String(),
                    'destination_number' => $returnedCall->destination_number,
                ] : null,
            ];
        })->values();

        return [
            'period' => [
                'from' => $from->copy()->startOfDay()->toIso8601String(),
                'to' => $to->copy()->endOfDay()->toIso8601String(),
            ],
            'returned_call_window_days' => $windowDays,
            'summary' => [
                'voicemails' => $items->count(),
                'pending_follow_up' => $items->where('follow_up_status', 'pending')->count(),
                'needs_review' => $items->filter(fn (array $item) => (bool) data_get($item, 'recording.needs_review', false))->count(),
                'needs_attention' => $items->where('needs_attention', true)->count(),
            ],
            'items' => $items->all(),
        ];
    }

    protected function resolveVoicemailRecording(Organization $organization, CallEventLog $event): ?Recording
    {
        $storagePath = data_get($event->payload, 'metadata.storage_path');

        return Recording::query()
            ->where('organization_id', $organization->id)
            ->when($event->call_uuid, fn ($query) => $query->where('call_uuid', $event->call_uuid))
            ->when($storagePath, fn ($query) => $query->orWhere('file_path', $storagePath))
            ->orderByDesc('created_at')
            ->first();
    }
}
