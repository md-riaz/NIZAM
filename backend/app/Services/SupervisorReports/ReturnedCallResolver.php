<?php

namespace App\Services\SupervisorReports;

use App\Models\CallDetailRecord;
use App\Models\Tenant;
use App\Services\DidNormalizationService;
use Carbon\CarbonInterface;

class ReturnedCallResolver
{
    public function defaultWindowDays(): int
    {
        return max(1, (int) config('services.supervisor_reports.returned_call_window_days', 7));
    }

    public function normalizeNumber(?string $number): string
    {
        $value = trim((string) $number);

        if ($value === '') {
            return '';
        }

        return DidNormalizationService::toDigitsOnly(
            DidNormalizationService::toE164($value)
        );
    }

    public function findReturnedCall(
        Tenant $tenant,
        string $normalizedCallerNumber,
        CarbonInterface $after,
        ?int $windowDays = null
    ): ?CallDetailRecord {
        if ($normalizedCallerNumber === '') {
            return null;
        }

        $windowDays ??= $this->defaultWindowDays();
        $deadline = $after->copy()->addDays($windowDays);

        return CallDetailRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('direction', 'outbound')
            ->where('start_stamp', '>', $after)
            ->where('start_stamp', '<=', $deadline)
            ->get()
            ->first(function (CallDetailRecord $cdr) use ($normalizedCallerNumber): bool {
                return $this->normalizeNumber($cdr->destination_number) === $normalizedCallerNumber;
            });
    }
}
