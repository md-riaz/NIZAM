<?php

namespace App\Services\SupervisorReports;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use Carbon\CarbonInterface;

class MissedReturnedCallsReportService
{
    public function __construct(
        protected ReturnedCallResolver $returnedCallResolver,
    ) {}

    public function generate(Organization $organization, CarbonInterface $from, CarbonInterface $to, ?int $windowDays = null): array
    {
        $windowDays ??= $this->returnedCallResolver->defaultWindowDays();

        $missedCalls = CallDetailRecord::query()
            ->where('organization_id', $organization->id)
            ->where('direction', 'inbound')
            ->whereNull('answer_stamp')
            ->whereBetween('start_stamp', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('start_stamp')
            ->get();

        $items = $missedCalls->map(function (CallDetailRecord $cdr) use ($organization, $windowDays): array {
            $normalizedCallerNumber = $this->returnedCallResolver->normalizeNumber($cdr->caller_id_number);
            $returnedCall = $this->returnedCallResolver->findReturnedCall(
                $organization,
                $normalizedCallerNumber,
                $cdr->start_stamp,
                $windowDays,
            );

            return [
                'cdr_id' => $cdr->id,
                'call_uuid' => $cdr->uuid,
                'caller_id_number' => $cdr->caller_id_number,
                'normalized_caller_number' => $normalizedCallerNumber,
                'destination_number' => $cdr->destination_number,
                'missed_at' => $cdr->start_stamp?->toIso8601String(),
                'returned' => $returnedCall !== null,
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
                'missed_calls' => $items->count(),
                'returned_calls' => $items->where('returned', true)->count(),
                'open_missed_calls' => $items->where('returned', false)->count(),
            ],
            'items' => $items->all(),
        ];
    }
}
