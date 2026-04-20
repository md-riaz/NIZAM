<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\UsageRecord;
use Carbon\Carbon;

class UsageMeteringService
{
    /**
     * Record a usage metric for an organization.
     */
    public function record(Organization $organization, string $metric, float $value, ?array $metadata = null, ?Carbon $date = null): UsageRecord
    {
        $date = $date ?? Carbon::today();

        return $organization->usageRecords()->create([
            'metric' => $metric,
            'value' => $value,
            'metadata' => $metadata,
            'recorded_date' => $date->toDateString(),
        ]);
    }

    /**
     * Collect and record current snapshot metrics for an organization.
     */
    public function collectSnapshot(Organization $organization, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();
        $records = [];

        $records[] = $this->record(
            $organization,
            UsageRecord::METRIC_ACTIVE_EXTENSIONS,
            $organization->extensions()->where('is_active', true)->count(),
            null,
            $date
        );

        $records[] = $this->record(
            $organization,
            UsageRecord::METRIC_RECORDING_STORAGE,
            (float) $organization->recordings()->sum('file_size'),
            null,
            $date
        );

        $records[] = $this->record(
            $organization,
            UsageRecord::METRIC_ACTIVE_DEVICES,
            (float) $organization->deviceProfiles()->count(),
            null,
            $date
        );

        return $records;
    }

    /**
     * Get usage summary for an organization within a date range.
     */
    public function getSummary(Organization $organization, Carbon $from, Carbon $to): array
    {
        $records = $organization->usageRecords()
            ->whereDate('recorded_date', '>=', $from->toDateString())
            ->whereDate('recorded_date', '<=', $to->toDateString())
            ->get();

        $summary = [];

        foreach ($records->groupBy('metric') as $metric => $metricRecords) {
            $summary[$metric] = [
                'total' => round((float) $metricRecords->sum('value'), 4),
                'peak' => round((float) $metricRecords->max('value'), 4),
                'average' => round((float) $metricRecords->avg('value'), 4),
                'count' => $metricRecords->count(),
            ];
        }

        return $summary;
    }

    /**
     * Reconcile CDR billable seconds against metered call_minutes for an organization.
     *
     * Compares the sum of CDR billsec (converted to minutes) with the sum of
     * recorded call_minutes usage records for the given date range.
     */
    public function reconcileCallMinutes(Organization $organization, Carbon $from, Carbon $to): array
    {
        $fromDate = $from->copy()->startOfDay();
        $toDate = $to->copy()->endOfDay();

        $cdrTotalSeconds = (int) $organization->cdrs()
            ->whereBetween('start_stamp', [$fromDate, $toDate])
            ->sum('billsec');

        $cdrMinutes = round($cdrTotalSeconds / 60, 4);

        $meteredMinutes = (float) $organization->usageRecords()
            ->where('metric', UsageRecord::METRIC_CALL_MINUTES)
            ->whereDate('recorded_date', '>=', $from->copy()->toDateString())
            ->whereDate('recorded_date', '<=', $to->copy()->toDateString())
            ->sum('value');

        $meteredMinutes = round($meteredMinutes, 4);

        return [
            'cdr_total_seconds' => $cdrTotalSeconds,
            'cdr_total_minutes' => $cdrMinutes,
            'metered_minutes' => $meteredMinutes,
            'difference_minutes' => round($cdrMinutes - $meteredMinutes, 4),
            'matched' => abs($cdrMinutes - $meteredMinutes) < 0.01,
        ];
    }
}
