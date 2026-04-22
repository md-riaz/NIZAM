<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\SystemSetting;
use Closure;
use RuntimeException;

class ExtensionNumberingService
{
    public function range(): array
    {
        $start = SystemSetting::platformInteger(SystemSetting::EXTENSION_RANGE_START, 101) ?? 101;
        $end = SystemSetting::platformInteger(SystemSetting::EXTENSION_RANGE_END, 500) ?? 500;

        if ($start > $end) {
            throw new RuntimeException('Invalid global extension range configuration.');
        }

        return [$start, $end];
    }

    public function validate(string $extension, Closure $fail): void
    {
        if (! ctype_digit($extension)) {
            $fail('Extension must contain digits only.');
            return;
        }

        [$start, $end] = $this->range();
        $value = (int) $extension;

        if ($value < $start || $value > $end) {
            $fail(sprintf('Extension must be between %d and %d.', $start, $end));
        }
    }

    public function firstAvailableForOrganization(Organization $organization): string
    {
        [$start, $end] = $this->range();
        $taken = $organization->extensions()->pluck('extension')->all();
        $takenMap = array_fill_keys($taken, true);

        for ($number = $start; $number <= $end; $number++) {
            $candidate = (string) $number;

            if (! isset($takenMap[$candidate])) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf('No extension numbers available between %d and %d.', $start, $end));
    }
}
