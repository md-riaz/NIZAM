<?php

namespace App\Services;

use App\Data\FlowData;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\Flow\FlowApplicationService;
use App\Services\Organization\OrganizationBootstrapPresetService;
use App\Services\StarterExtensionProvisioningService;
use Illuminate\Support\Arr;

class OrganizationEntrypointProvisioningService
{
    public function __construct(
        protected FlowApplicationService $flowApplicationService,
        protected OrganizationManifestBuilder $organizationManifestBuilder,
        protected StarterExtensionProvisioningService $starterExtensionProvisioningService,
        protected OrganizationBootstrapPresetService $organizationBootstrapPresetService,
    ) {}

    public function provision(Organization $organization): Organization
    {
        $schedule = $this->provisionDefaultSchedule($organization);
        $starterExtension = $this->starterExtensionProvisioningService->provision($organization);
        [$targetType, $targetId] = $this->resolveOpenTarget($organization, $starterExtension);
        $preset = $this->organizationBootstrapPresetService->defaultPreset(
            $schedule,
            $targetType,
            $targetId,
            $starterExtension->extension,
        );
        $flow = $this->provisionStarterMainFlow($organization, $preset);

        $settings = $organization->settings ?? [];
        $settings['timezone'] ??= (string) config('telephony.bootstrap.default_timezone', 'Asia/Dhaka');
        $settings['country'] ??= (string) config('telephony.bootstrap.default_country', 'Bangladesh');
        $settings['default_country_code'] ??= '880';

        Arr::set($settings, 'business_phone.default_entrypoint', array_merge(
            $preset['default_entrypoint'],
            ['flow_id' => (string) $flow->id],
        ));
        Arr::set(
            $settings,
            'business_phone.office_features',
            $this->organizationBootstrapPresetService->normalizeOfficeFeatures(
                is_array(data_get($settings, 'business_phone.office_features'))
                    ? data_get($settings, 'business_phone.office_features')
                    : []
            )
        );

        $organization->forceFill([
            'default_schedule_id' => $schedule->id,
            'settings' => $settings,
        ])->save();

        if (! $organization->relationLoaded('defaultSchedule')) {
            $organization->loadMissing('defaultSchedule');
        }

        if (! $organization->defaultSchedule?->holiday_calendar_id && $organization->default_holiday_calendar_id) {
            $organization->defaultSchedule->update([
                'holiday_calendar_id' => $organization->default_holiday_calendar_id,
            ]);
        }

        $organization->refresh();

        $this->organizationManifestBuilder->buildAndActivate($organization->fresh());

        return $organization->fresh(['defaultSchedule', 'flows.activeVersion', 'dids', 'extensions', 'teams.members']);
    }

    protected function provisionDefaultSchedule(Organization $organization): Schedule
    {
        if ($organization->defaultSchedule) {
            return $organization->defaultSchedule;
        }

        $schedule = $organization->schedules()->create([
            'holiday_calendar_id' => $organization->default_holiday_calendar_id,
            'name' => 'Main Business Hours',
            'timezone' => (string) data_get($organization->settings, 'timezone', config('telephony.bootstrap.default_timezone', 'Asia/Dhaka')),
            'is_active' => true,
        ]);

        foreach ((array) config('telephony.bootstrap.business_hours.days', [1, 2, 3, 4, 5]) as $dayOfWeek) {
            ScheduleRule::create([
                'schedule_id' => $schedule->id,
                'day_of_week' => (int) $dayOfWeek,
                'start_time' => (string) config('telephony.bootstrap.business_hours.start', '09:00'),
                'end_time' => (string) config('telephony.bootstrap.business_hours.end', '17:00'),
            ]);
        }

        return $schedule;
    }

    /**
     * @param  array<string, mixed>  $preset
     */
    protected function provisionStarterMainFlow(Organization $organization, array $preset): Flow
    {
        $existingFlowId = data_get($organization->settings, 'business_phone.default_entrypoint.flow_id');

        if (is_string($existingFlowId) && $existingFlowId !== '') {
            $existingFlow = $organization->flows()->find($existingFlowId);

            if ($existingFlow) {
                $flow = $this->flowApplicationService->update($existingFlow, new FlowData(
                    name: (string) data_get($preset, 'flow.name', 'Main Business Phone'),
                    description: (string) data_get($preset, 'flow.description', 'Starter business-hours entrypoint with after-hours voicemail fallback.'),
                    definition: (array) data_get($preset, 'flow.definition', []),
                    publish: true,
                ));

                $this->provisionStarterDid($organization, $flow);

                return $flow->fresh(['activeVersion']);
            }
        }

        $flow = $this->flowApplicationService->create($organization->id, new FlowData(
            name: (string) data_get($preset, 'flow.name', 'Main Business Phone'),
            description: (string) data_get($preset, 'flow.description', 'Starter business-hours entrypoint with after-hours voicemail fallback.'),
            definition: (array) data_get($preset, 'flow.definition', []),
            publish: true,
        ));

        $this->provisionStarterDid($organization, $flow);

        return $flow->fresh(['activeVersion']);
    }

    protected function provisionStarterDid(Organization $organization, Flow $flow): void
    {
        $did = $organization->dids()->firstWhere('description', 'Default Business Phone Entrypoint');

        if (! $did) {
            $organization->dids()->create([
                'number' => $this->starterEntrypointNumber($organization),
                'description' => 'Default Business Phone Entrypoint',
                'destination_type' => 'flow',
                'destination_id' => $flow->id,
                'is_active' => true,
            ]);

            return;
        }

        $did->forceFill([
            'destination_type' => 'flow',
            'destination_id' => $flow->id,
            'is_active' => true,
        ])->save();
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function resolveOpenTarget(Organization $organization, Extension $starterExtension): array
    {
        $existingTeam = $organization->teams()
            ->where('name', 'Main Team')
            ->where('is_active', true)
            ->first();

        if ($existingTeam) {
            $this->ensureTeamIncludesStarterExtension($existingTeam, $starterExtension);

            return ['team', $existingTeam->id];
        }

        return ['extension', $starterExtension->id];
    }

    protected function ensureTeamIncludesStarterExtension(Team $team, Extension $starterExtension): void
    {
        $member = $team->members()
            ->where('endpoint_type', 'extension')
            ->where('endpoint_id', $starterExtension->id)
            ->first();

        if ($member) {
            if (! $member->is_active) {
                $member->forceFill(['is_active' => true])->save();
            }

            return;
        }

        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $starterExtension->id,
            'priority' => (int) $team->members()->max('priority') + 1,
            'is_active' => true,
        ]);
    }

    protected function starterEntrypointNumber(Organization $organization): string
    {
        $numericSeed = sprintf('%010u', crc32((string) $organization->id));

        return '+1999'.$numericSeed;
    }
}
