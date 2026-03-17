<?php

namespace App\Observers;

use App\Services\TenantManifestBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ScheduleChildObserver
{
    public function __construct(
        protected TenantManifestBuilder $manifestBuilder
    ) {}

    protected function rebuildManifest(Model $model): void
    {
        try {
            if ($model->schedule && $model->schedule->tenant) {
                $this->manifestBuilder->buildAndActivate($model->schedule->tenant);
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
