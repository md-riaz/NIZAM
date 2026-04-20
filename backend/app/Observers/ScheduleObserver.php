<?php

namespace App\Observers;

use App\Models\Schedule;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Support\Facades\Log;

class ScheduleObserver
{
    public function __construct(
        protected OrganizationManifestBuilder $manifestBuilder
    ) {}

    protected function rebuildManifest(Schedule $schedule): void
    {
        try {
            $this->manifestBuilder->buildAndActivate($schedule->organization);
        } catch (\Exception $e) {
            Log::error('Failed to rebuild manifest on schedule change', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function created(Schedule $schedule): void { $this->rebuildManifest($schedule); }
    public function updated(Schedule $schedule): void { $this->rebuildManifest($schedule); }
    public function deleted(Schedule $schedule): void { $this->rebuildManifest($schedule); }
}
