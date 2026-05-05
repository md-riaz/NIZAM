<?php

namespace App\Observers;

use App\Models\Team;
use App\Models\TeamMember;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ScheduleChildObserver
{
    public function __construct(
        protected OrganizationManifestBuilder $manifestBuilder
    ) {}

    protected function rebuildManifest(Model $model): void
    {
        try {
            $organization = match (true) {
                $model instanceof Team => $model->organization,
                $model instanceof TeamMember => $model->team?->organization,
                default => $model->schedule?->organization,
            };

            if ($organization) {
                $this->manifestBuilder->buildAndActivate($organization);
            }
        } catch (\Exception $e) {
            Log::error('Failed to rebuild manifest on schedule child change', [
                'model' => get_class($model),
                'id' => $model->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function created(Model $model): void { $this->rebuildManifest($model); }
    public function updated(Model $model): void { $this->rebuildManifest($model); }
    public function deleted(Model $model): void { $this->rebuildManifest($model); }
}
