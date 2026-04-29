<?php

namespace App\Observers;

use App\Models\CallRoutingPolicy;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\Ivr;
use App\Models\RingGroup;
use App\Models\Schedule;
use App\Models\ScheduleBreak;
use App\Models\ScheduleException;
use App\Models\ScheduleRule;
use App\Models\Organization;
use App\Models\TimeCondition;
use App\Services\OrganizationManifestBuilder;

trait RebuildsOrganizationManifest
{
    protected function rebuildOrganizationManifestForModel(object $model): void
    {
        $organization = $this->resolveOrganization($model);

        if (! $organization) {
            return;
        }

        app(OrganizationManifestBuilder::class)->buildAndActivate($organization);
    }

    protected function resolveOrganization(object $model): ?Organization
    {
        return match (true) {
            $model instanceof Organization => $model,
            $model instanceof Schedule => $model->organization,
            $model instanceof HolidayCalendar => $model->organization,
            $model instanceof Did => $model->organization,
            $model instanceof Extension => $model->organization,
            $model instanceof RingGroup => $model->organization,
            $model instanceof Ivr => $model->organization,
            $model instanceof TimeCondition => $model->organization,
            $model instanceof CallRoutingPolicy => $model->organization,
            $model instanceof ScheduleRule,
            $model instanceof ScheduleBreak,
            $model instanceof ScheduleException => $model->schedule?->organization,
            $model instanceof Holiday => $model->holidayCalendar?->organization,
            default => null,
        };
    }
}
