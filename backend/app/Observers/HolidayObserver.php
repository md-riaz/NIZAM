<?php

namespace App\Observers;

use App\Models\Holiday;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Support\Facades\Log;

class HolidayObserver
{
    public function __construct(
        protected OrganizationManifestBuilder $manifestBuilder
    ) {}

    protected function rebuildManifest(Holiday $holiday): void
    {
        try {
            if ($holiday->holidayCalendar && $holiday->holidayCalendar->organization) {
                $this->manifestBuilder->buildAndActivate($holiday->holidayCalendar->organization);
            }
        } catch (\Exception $e) {
            Log::error('Failed to rebuild manifest on holiday change', [
                'holiday_id' => $holiday->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function created(Holiday $holiday): void { $this->rebuildManifest($holiday); }
    public function updated(Holiday $holiday): void { $this->rebuildManifest($holiday); }
    public function deleted(Holiday $holiday): void { $this->rebuildManifest($holiday); }
}
