<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\UsageRecord;
use Carbon\Carbon;

class UsageMeteringService
{
    /**
     * Record a usage metric for a tenant.
     */
    public function record(Tenant $tenant, string $metric, float $value, ?array $metadata = null, ?Carbon $date = null): UsageRecord
    {
        $date = $date ?? Carbon::today();

        return $tenant->usageRecords()->create([
            'metric' => $metric,
            'value' => $value,
            'metadata' => $metadata,
            'recorded_date' => $date->toDateString(),
        ]);
    }

    /**
     * Collect and record current snapshot metrics for a tenant.
     */
    public function collectSnapshot(Tenant $tenant, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();
        $records = [];

        $records[] = $this->record(
            $tenant,
            UsageRecord::METRIC_ACTIVE_EXTENSIONS,
            $tenant->extensions()->where('is_active', true)->count(),
            null,
            $date
        );

        $records[] = $this->record(
            $tenant,
            UsageRecord::METRIC_RECORDING_STORAGE,
            (float) $tenant->recordings()->sum('file_size'),
            null,
            $date
        );

        $records[] = $this->record(
            $tenant,
            UsageRecord::METRIC_ACTIVE_DEVICES,
            (float) $tenant->deviceProfiles()->count(),
            null,
            $date
        );

        return $records;
    }

    /**
     * Get usage summary for a tenant within a date range.
     */
    public function getSummary(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        $records = $tenant->usageRecords()
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
     * Reconcile CDR billable seconds against metered call_minutes for a tenant.
     *
     * Compares the sum of CDR billsec (converted to minutes) with the sum of
     * recorded call_minutes usage records for the given date range.
     */
    public function reconcileCallMinutes(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        $fromDate = $from->copy()->startOfDay();
        $toDate = $to->copy()->endOfDay();

        $cdrTotalSeconds = (int) $tenant->cdrs()
            ->whereBetween('start_stamp', [$fromDate, $toDate])
            ->sum('billsec');

        $cdrMinutes = round($cdrTotalSeconds / 60, 4);

        $meteredMinutes = (float) $tenant->usageRecords()
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
