<?php

namespace App\Observers;

use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\Schedule;
use App\Models\ScheduleBreak;
use App\Models\ScheduleException;
use App\Models\ScheduleRule;
use App\Models\Tenant;
use App\Services\TenantManifestBuilder;

trait RebuildsTenantManifest
{
    protected function rebuildTenantManifestForModel(object $model): void
    {
        $tenant = $this->resolveTenant($model);

        if (! $tenant) {
            return;
        }

        app(TenantManifestBuilder::class)->buildAndActivate($tenant);
    }

    protected function resolveTenant(object $model): ?Tenant
    {
        return match (true) {
            $model instanceof Tenant => $model,
            $model instanceof Schedule => $model->tenant,
            $model instanceof HolidayCalendar => $model->tenant,
            $model instanceof ScheduleRule,
            $model instanceof ScheduleBreak,
            $model instanceof ScheduleException => $model->schedule?->tenant,
            $model instanceof Holiday => $model->holidayCalendar?->tenant,
            default => null,
        };
    }
}
