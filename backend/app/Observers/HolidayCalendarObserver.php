<?php

namespace App\Observers;

use App\Models\HolidayCalendar;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Support\Facades\Log;

class HolidayCalendarObserver
{
    public function __construct(
        protected OrganizationManifestBuilder $manifestBuilder
    ) {}

    protected function rebuildManifest(HolidayCalendar $calendar): void
    {
        try {
            $this->manifestBuilder->buildAndActivate($calendar->organization);
        } catch (\Exception $e) {
            Log::error('Failed to rebuild manifest on holiday calendar change', [
                'calendar_id' => $calendar->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function created(HolidayCalendar $calendar): void { $this->rebuildManifest($calendar); }
    public function updated(HolidayCalendar $calendar): void { $this->rebuildManifest($calendar); }
    public function deleted(HolidayCalendar $calendar): void { $this->rebuildManifest($calendar); }
}
