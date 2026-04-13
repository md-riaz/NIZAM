<?php

namespace App\Observers;

use App\Models\CallRoutingPolicy;
use App\Models\Did;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\Ivr;
use App\Models\RingGroup;
use App\Models\Schedule;
use App\Models\ScheduleBreak;
use App\Models\ScheduleException;
use App\Models\ScheduleRule;
use App\Models\Tenant;
use App\Models\TimeCondition;
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
            $model instanceof Did => $model->tenant,
            $model instanceof RingGroup => $model->tenant,
            $model instanceof Ivr => $model->tenant,
            $model instanceof TimeCondition => $model->tenant,
            $model instanceof CallRoutingPolicy => $model->tenant,
            $model instanceof ScheduleRule,
            $model instanceof ScheduleBreak,
            $model instanceof ScheduleException => $model->schedule?->tenant,
            $model instanceof Holiday => $model->holidayCalendar?->tenant,
            default => null,
        };
    }
}
