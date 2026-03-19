<?php

namespace App\Services\Cdr;

use App\Models\CallDetailRecord;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CdrAnalyticsService
{
    /**
     * Get a summary of CDR analytics for a tenant.
     */
    public function getSummary(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        $query = CallDetailRecord::where('tenant_id', $tenant->id)
            ->whereBetween('start_stamp', [$from->startOfDay(), $to->endOfDay()]);

        $totalCalls = (clone $query)->count();
        $answeredCalls = (clone $query)->whereNotNull('answer_stamp')->count();
        $totalDuration = (clone $query)->sum('duration');
        $totalBillsec = (clone $query)->sum('billsec');

        $avgDuration = $answeredCalls > 0
            ? round((clone $query)->whereNotNull('answer_stamp')->avg('duration'), 1)
            : 0;

        // Answer Seizure Ratio (ASR) = answered calls / total calls * 100
        $asr = $totalCalls > 0
            ? round(($answeredCalls / $totalCalls) * 100, 2)
            : 0;

        // Average Call Duration (ACD) = total billsec / answered calls
        $acd = $answeredCalls > 0
            ? round($totalBillsec / $answeredCalls, 1)
            : 0;

        // Average quality score
        $avgQuality = (clone $query)->whereNotNull('quality_score')->avg('quality_score');
        $avgMos = (clone $query)->whereNotNull('mos_score')->avg('mos_score');

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'missed_calls' => (clone $query)->where('hangup_cause', 'NO_ANSWER')->count(),
            'failed_calls' => (clone $query)->whereNotIn('hangup_cause', ['NORMAL_CLEARING', 'NO_ANSWER', 'ORIGINATOR_CANCEL'])->count(),
            'total_duration_seconds' => (int) $totalDuration,
            'total_billsec_seconds' => (int) $totalBillsec,
            'average_duration_seconds' => (float) $avgDuration,
            'asr' => (float) $asr,
            'acd_seconds' => (float) $acd,
            'quality' => [
                'average_score' => $avgQuality !== null ? round((float) $avgQuality, 1) : null,
                'average_mos' => $avgMos !== null ? round((float) $avgMos, 2) : null,
            ],
            'by_direction' => $this->getCountByDirection($tenant, $from, $to),
            'by_call_type' => $this->getCountByCallType($tenant, $from, $to),
        ];
    }

    /**
     * Get call volume over time (hourly or daily buckets).
     */
    public function getVolume(Tenant $tenant, Carbon $from, Carbon $to, string $granularity = 'daily'): array
    {
        $dateFormat = $granularity === 'hourly' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';
        $dateFormatSql = $granularity === 'hourly' ? "DATE_FORMAT(start_stamp, '{$dateFormat}')" : 'DATE(start_stamp)';

        $results = CallDetailRecord::where('tenant_id', $tenant->id)
            ->whereBetween('start_stamp', [$from->startOfDay(), $to->endOfDay()])
            ->select(
                DB::raw("{$dateFormatSql} as period"),
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('SUM(CASE WHEN answer_stamp IS NOT NULL THEN 1 ELSE 0 END) as answered_calls'),
                DB::raw('SUM(duration) as total_duration'),
                DB::raw('SUM(billsec) as total_billsec'),
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $results->map(fn ($row) => [
            'period' => $row->period,
            'total_calls' => (int) $row->total_calls,
            'answered_calls' => (int) $row->answered_calls,
            'total_duration_seconds' => (int) $row->total_duration,
            'total_billsec_seconds' => (int) $row->total_billsec,
            'asr' => $row->total_calls > 0
                ? round(($row->answered_calls / $row->total_calls) * 100, 2)
                : 0,
        ])->toArray();
    }

    /**
     * Get quality metrics trends over time.
     */
    public function getQualityTrends(Tenant $tenant, Carbon $from, Carbon $to, string $granularity = 'daily'): array
    {
        $dateFormatSql = $granularity === 'hourly' ? "DATE_FORMAT(start_stamp, '%Y-%m-%d %H:00:00')" : 'DATE(start_stamp)';

        $results = CallDetailRecord::where('tenant_id', $tenant->id)
            ->whereBetween('start_stamp', [$from->startOfDay(), $to->endOfDay()])
            ->whereNotNull('quality_score')
            ->select(
                DB::raw("{$dateFormatSql} as period"),
                DB::raw('AVG(quality_score) as avg_quality_score'),
                DB::raw('AVG(mos_score) as avg_mos'),
                DB::raw('AVG(packet_loss) as avg_packet_loss'),
                DB::raw('AVG(jitter) as avg_jitter'),
                DB::raw('AVG(latency) as avg_latency'),
                DB::raw('COUNT(*) as sample_count'),
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $results->map(fn ($row) => [
            'period' => $row->period,
            'avg_quality_score' => round((float) $row->avg_quality_score, 1),
            'avg_mos' => $row->avg_mos !== null ? round((float) $row->avg_mos, 2) : null,
            'avg_packet_loss' => $row->avg_packet_loss !== null ? round((float) $row->avg_packet_loss, 2) : null,
            'avg_jitter_ms' => $row->avg_jitter !== null ? round((float) $row->avg_jitter, 1) : null,
            'avg_latency_ms' => $row->avg_latency !== null ? round((float) $row->avg_latency, 1) : null,
            'sample_count' => (int) $row->sample_count,
        ])->toArray();
    }

    /**
     * Get top destinations by call count.
     */
    public function getTopDestinations(Tenant $tenant, Carbon $from, Carbon $to, int $limit = 20): array
    {
        $results = CallDetailRecord::where('tenant_id', $tenant->id)
            ->whereBetween('start_stamp', [$from->startOfDay(), $to->endOfDay()])
            ->select(
                'destination_number',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('SUM(CASE WHEN answer_stamp IS NOT NULL THEN 1 ELSE 0 END) as answered_calls'),
                DB::raw('SUM(duration) as total_duration'),
                DB::raw('SUM(billsec) as total_billsec'),
                DB::raw('AVG(CASE WHEN quality_score IS NOT NULL THEN quality_score END) as avg_quality'),
            )
            ->groupBy('destination_number')
            ->orderByDesc('total_calls')
            ->limit($limit)
            ->get();

        return $results->map(fn ($row) => [
            'destination_number' => $row->destination_number,
            'total_calls' => (int) $row->total_calls,
            'answered_calls' => (int) $row->answered_calls,
            'total_duration_seconds' => (int) $row->total_duration,
            'total_billsec_seconds' => (int) $row->total_billsec,
            'asr' => $row->total_calls > 0
                ? round(($row->answered_calls / $row->total_calls) * 100, 2)
                : 0,
            'avg_quality_score' => $row->avg_quality !== null ? round((float) $row->avg_quality, 1) : null,
        ])->toArray();
    }

    /**
     * Get call count by direction.
     */
    protected function getCountByDirection(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        return CallDetailRecord::where('tenant_id', $tenant->id)
            ->whereBetween('start_stamp', [$from->startOfDay(), $to->endOfDay()])
            ->select('direction', DB::raw('COUNT(*) as count'))
            ->groupBy('direction')
            ->pluck('count', 'direction')
            ->toArray();
    }

    /**
     * Get call count by call type.
     */
    protected function getCountByCallType(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        return CallDetailRecord::where('tenant_id', $tenant->id)
            ->whereBetween('start_stamp', [$from->startOfDay(), $to->endOfDay()])
            ->whereNotNull('call_type')
            ->select('call_type', DB::raw('COUNT(*) as count'))
            ->groupBy('call_type')
            ->pluck('count', 'call_type')
            ->toArray();
    }
}
