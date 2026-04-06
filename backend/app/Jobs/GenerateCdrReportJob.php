<?php

namespace App\Jobs;

use App\Models\CallDetailRecord;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateCdrReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public Tenant $tenant,
        public string $reportType, // 'daily', 'weekly', 'monthly'
        public ?Carbon $reportDate = null,
    ) {
        $this->reportDate = $reportDate ?? Carbon::today();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        [$from, $to] = $this->getDateRange();

        $cdrs = CallDetailRecord::where('tenant_id', $this->tenant->id)
            ->whereBetween('start_stamp', [$from, $to])
            ->orderBy('start_stamp', 'desc')
            ->get();

        $totalCalls = $cdrs->count();
        $answeredCalls = $cdrs->whereNotNull('answer_stamp')->count();
        $totalDuration = $cdrs->sum('duration');
        $totalBillsec = $cdrs->sum('billsec');
        $asr = $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 2) : 0;
        $acd = $answeredCalls > 0 ? round($totalBillsec / $answeredCalls, 1) : 0;

        $report = [
            'tenant_id' => $this->tenant->id,
            'report_type' => $this->reportType,
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_calls' => $totalCalls,
                'answered_calls' => $answeredCalls,
                'missed_calls' => $cdrs->where('hangup_cause', 'NO_ANSWER')->count(),
                'total_duration_seconds' => (int) $totalDuration,
                'total_billsec_seconds' => (int) $totalBillsec,
                'asr' => $asr,
                'acd_seconds' => $acd,
            ],
            'by_direction' => $cdrs->groupBy('direction')->map->count()->toArray(),
            'by_call_type' => $cdrs->whereNotNull('call_type')->groupBy('call_type')->map->count()->toArray(),
            'top_destinations' => $cdrs->groupBy('destination_number')
                ->sortByDesc(fn ($group) => $group->count())
                ->take(10)
                ->map(fn ($group, $dest) => [
                    'destination' => $dest,
                    'calls' => $group->count(),
                    'duration' => $group->sum('duration'),
                ])
                ->values()
                ->toArray(),
        ];

        // Store the report as JSON
        $filename = sprintf(
            'cdr-reports/%s/%s_%s.json',
            $this->tenant->id,
            $this->reportType,
            $from->format('Y-m-d')
        );

        Storage::put($filename, json_encode($report, JSON_PRETTY_PRINT));

        Log::info('CDR report generated', [
            'tenant_id' => $this->tenant->id,
            'report_type' => $this->reportType,
            'filename' => $filename,
            'total_calls' => $totalCalls,
        ]);
    }

    /**
     * Get the date range for the report.
     */
    protected function getDateRange(): array
    {
        return match ($this->reportType) {
            'daily' => [
                $this->reportDate->copy()->startOfDay(),
                $this->reportDate->copy()->endOfDay(),
            ],
            'weekly' => [
                $this->reportDate->copy()->startOfWeek(),
                $this->reportDate->copy()->endOfWeek(),
            ],
            'monthly' => [
                $this->reportDate->copy()->startOfMonth(),
                $this->reportDate->copy()->endOfMonth(),
            ],
            default => [
                $this->reportDate->copy()->startOfDay(),
                $this->reportDate->copy()->endOfDay(),
            ],
        };
    }
}
