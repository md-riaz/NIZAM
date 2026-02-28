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
            ->whereBetween('recorded_date', [$from->toDateString(), $to->toDateString()])
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
}
