<?php

namespace App\Services\SupervisorReports;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CallSummaryReportService
{
    public function generate(Organization $organization, CarbonInterface $from, CarbonInterface $to): array
    {
        $query = CallDetailRecord::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('start_stamp', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $totalCalls = (clone $query)->count();
        $answeredCalls = (clone $query)->whereNotNull('answer_stamp')->count();
        $missedCalls = (clone $query)->where('direction', 'inbound')->whereNull('answer_stamp')->count();
        $voicemailCalls = (clone $query)
            ->where(function ($builder) {
                $builder->where('hangup_cause', 'VOICEMAIL')
                    ->orWhere('destination_number', 'voicemail');
            })
            ->count();

        $totalDuration = (int) (clone $query)->sum('duration');
        $totalBillsec = (int) (clone $query)->sum('billsec');

        $directionCounts = (clone $query)
            ->select('direction', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('direction')
            ->pluck('aggregate', 'direction')
            ->map(fn ($count) => (int) $count)
            ->toArray();

        return [
            'period' => [
                'from' => $from->copy()->startOfDay()->toIso8601String(),
                'to' => $to->copy()->endOfDay()->toIso8601String(),
            ],
            'totals' => [
                'calls' => $totalCalls,
                'answered_calls' => $answeredCalls,
                'missed_calls' => $missedCalls,
                'voicemail_calls' => $voicemailCalls,
                'total_duration_seconds' => $totalDuration,
                'total_billsec_seconds' => $totalBillsec,
                'answer_rate' => $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 2) : 0.0,
            ],
            'by_direction' => $directionCounts,
        ];
    }
}
