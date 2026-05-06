<?php

namespace App\Services\Organization;

use App\Models\Did;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;

class OrganizationProvisioningHealthService
{
    /**
     * @return array{
     *   status:string,
     *   summary:string,
     *   warning_count:int,
     *   blocker_count:int,
     *   checks:list<array{key:string,status:string,message:string}>,
     *   next_actions:list<string>
     * }
     */
    public function evaluate(Organization $organization): array
    {
        $organization->loadMissing([
            'defaultSchedule',
            'defaultHolidayCalendar',
            'dids',
            'teams',
            'flows',
            'extensions',
            'activeInboundRoutingManifest',
        ]);

        $checks = [
            $this->defaultScheduleCheck($organization),
            $this->defaultHolidayCalendarCheck($organization),
            $this->entrypointDidCheck($organization),
            $this->openTargetCheck($organization),
            $this->activeManifestCheck($organization),
        ];

        $warningCount = count(array_filter($checks, fn (array $check): bool => $check['status'] === 'warning'));
        $blockerCount = count(array_filter($checks, fn (array $check): bool => $check['status'] === 'blocked'));

        return [
            'status' => $blockerCount > 0 ? 'blocked' : ($warningCount > 0 ? 'warning' : 'ready'),
            'summary' => $this->summaryFor($blockerCount, $warningCount),
            'warning_count' => $warningCount,
            'blocker_count' => $blockerCount,
            'checks' => $checks,
            'next_actions' => $this->nextActionsFor($checks),
        ];
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function defaultScheduleCheck(Organization $organization): array
    {
        $schedule = $organization->defaultSchedule;

        if (! $schedule) {
            return $this->check('default_schedule', 'blocked', 'Default schedule is missing.');
        }

        if (! $schedule->is_active) {
            return $this->check('default_schedule', 'blocked', 'Default schedule is inactive.');
        }

        return $this->check('default_schedule', 'ok', 'Default schedule is active.');
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function defaultHolidayCalendarCheck(Organization $organization): array
    {
        if (! $organization->default_holiday_calendar_id) {
            return $this->check('default_holiday_calendar', 'warning', 'Default holiday calendar is not selected.');
        }

        $holidayCalendar = $organization->defaultHolidayCalendar;

        if (! $holidayCalendar) {
            return $this->check('default_holiday_calendar', 'warning', 'Default holiday calendar reference is missing.');
        }

        if (! $holidayCalendar->is_active) {
            return $this->check('default_holiday_calendar', 'warning', 'Default holiday calendar is inactive.');
        }

        return $this->check('default_holiday_calendar', 'ok', 'Default holiday calendar is active.');
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function entrypointDidCheck(Organization $organization): array
    {
        $entrypoint = $this->entrypointDid($organization);

        if (! $entrypoint) {
            return $this->check('entrypoint_did', 'blocked', 'Default entrypoint DID is missing.');
        }

        if (! $entrypoint->is_active) {
            return $this->check('entrypoint_did', 'blocked', 'Default entrypoint DID is inactive.');
        }

        if ($entrypoint->destination_type !== 'flow' || blank($entrypoint->destination_id)) {
            return $this->check('entrypoint_did', 'blocked', 'Default entrypoint DID is not routed to a flow.');
        }

        $flowExists = $organization->flows->contains('id', $entrypoint->destination_id)
            || $organization->flows()->whereKey($entrypoint->destination_id)->exists();

        if (! $flowExists) {
            return $this->check('entrypoint_did', 'blocked', 'Default entrypoint DID points to a missing flow.');
        }

        return $this->check('entrypoint_did', 'ok', 'Default entrypoint DID routes to an organization flow.');
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function openTargetCheck(Organization $organization): array
    {
        $entrypoint = data_get($organization->settings, 'business_phone.default_entrypoint');

        if (! is_array($entrypoint) || ! (bool) data_get($entrypoint, 'provisioned', false)) {
            return $this->check('open_target', 'blocked', 'Default entrypoint settings are not provisioned.');
        }

        $targetType = (string) data_get($entrypoint, 'open_target_type', '');
        $targetId = (string) data_get($entrypoint, 'open_target_id', '');

        if ($targetType === '' || $targetId === '') {
            return $this->check('open_target', 'blocked', 'Default entrypoint open target is missing.');
        }

        return match ($targetType) {
            'extension' => $this->extensionTargetCheck($organization, $targetId),
            'team' => $this->teamTargetCheck($organization, $targetId),
            default => $this->check('open_target', 'blocked', sprintf('Default entrypoint target type [%s] is unsupported.', $targetType)),
        };
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function activeManifestCheck(Organization $organization): array
    {
        if (! $organization->activeInboundRoutingManifest) {
            return $this->check('active_manifest', 'warning', 'No active inbound routing manifest is published.');
        }

        return $this->check('active_manifest', 'ok', 'Active inbound routing manifest is published.');
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function extensionTargetCheck(Organization $organization, string $targetId): array
    {
        $extension = $organization->extensions->firstWhere('id', $targetId)
            ?? $organization->extensions()->whereKey($targetId)->first();

        if (! $extension) {
            return $this->check('open_target', 'blocked', 'Default entrypoint extension target is missing.');
        }

        if (! $extension->is_active) {
            return $this->check('open_target', 'blocked', 'Default entrypoint extension target is inactive.');
        }

        return $this->check('open_target', 'ok', 'Default entrypoint extension target is active.');
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function teamTargetCheck(Organization $organization, string $targetId): array
    {
        $team = $organization->teams->firstWhere('id', $targetId)
            ?? $organization->teams()->whereKey($targetId)->first();

        if (! $team) {
            return $this->check('open_target', 'blocked', 'Default entrypoint team target is missing.');
        }

        if (! $team->is_active) {
            return $this->check('open_target', 'blocked', 'Default entrypoint team target is inactive.');
        }

        return $this->check('open_target', 'ok', 'Default entrypoint team target is active.');
    }

    private function entrypointDid(Organization $organization): ?Did
    {
        $did = $organization->dids->firstWhere('description', 'Default Business Phone Entrypoint');

        if ($did instanceof Did) {
            return $did;
        }

        return $organization->dids()
            ->where('description', 'Default Business Phone Entrypoint')
            ->first();
    }

    /**
     * @param  list<array{key:string,status:string,message:string}>  $checks
     * @return list<string>
     */
    private function nextActionsFor(array $checks): array
    {
        $actions = [];

        foreach ($checks as $check) {
            if ($check['status'] === 'ok') {
                continue;
            }

            match ($check['key']) {
                'entrypoint_did' => $actions[] = 'Assign main DID',
                'default_schedule' => $actions[] = 'Configure main business hours',
                'default_holiday_calendar' => $actions[] = 'Select office preset',
                'open_target' => $actions[] = 'Choose an active main destination',
                'active_manifest' => $actions[] = 'Publish inbound routing',
                default => null,
            };
        }

        return array_values(array_unique($actions));
    }

    private function summaryFor(int $blockerCount, int $warningCount): string
    {
        if ($blockerCount > 0) {
            return 'Organization provisioning is blocked.';
        }

        if ($warningCount > 0) {
            return 'Organization provisioning needs attention.';
        }

        return 'Organization provisioning is ready.';
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function check(string $key, string $status, string $message): array
    {
        return [
            'key' => $key,
            'status' => $status,
            'message' => $message,
        ];
    }
}
