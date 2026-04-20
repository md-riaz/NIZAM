<?php

namespace App\Services;

use App\Data\FlowData;
use App\Models\Did;
use App\Models\Flow;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Organization;
use App\Services\Flow\FlowApplicationService;

class OrganizationEntrypointProvisioningService
{
    public function __construct(
        protected FlowApplicationService $flowApplicationService,
        protected OrganizationManifestBuilder $organizationManifestBuilder,
    ) {}

    public function provision(Organization $organization): Organization
    {
        $schedule = $this->provisionDefaultSchedule($organization);
        $flow = $this->provisionStarterMainFlow($organization, $schedule);

        $settings = $organization->settings ?? [];
        $settings['timezone'] ??= (string) config('telephony.bootstrap.default_timezone', 'Asia/Dhaka');
        $settings['country'] ??= (string) config('telephony.bootstrap.default_country', 'Bangladesh');
        $settings['default_country_code'] ??= '880';
        $settings['business_phone'] = array_merge($settings['business_phone'] ?? [], [
            'default_entrypoint' => [
                'type' => 'flow',
                'flow_id' => $flow->id,
                'schedule_id' => $schedule->id,
                'provisioned' => true,
            ],
        ]);

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
        $schedule = $organization->defaultSchedule ?? $schedule;

        $this->organizationManifestBuilder->buildAndActivate($organization->fresh());

        return $organization->fresh(['defaultSchedule', 'flows.activeVersion', 'dids']);
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

    protected function provisionStarterMainFlow(Organization $organization, Schedule $schedule): Flow
    {
        $existingFlowId = data_get($organization->settings, 'business_phone.default_entrypoint.flow_id');

        if (is_string($existingFlowId) && $existingFlowId !== '') {
            $existingFlow = $organization->flows()->find($existingFlowId);

            if ($existingFlow) {
                return $existingFlow;
            }
        }

        $flow = $this->flowApplicationService->create($organization->id, new FlowData(
            name: 'Main Business Phone',
            description: 'Starter business-hours entrypoint with after-hours voicemail fallback.',
            definition: $this->starterFlowDefinition($schedule),
            publish: true,
        ));

        $did = $organization->dids()->firstWhere('description', 'Default Business Phone Entrypoint');

        if (! $did) {
            $organization->dids()->create([
                'number' => $this->starterEntrypointNumber($organization),
                'description' => 'Default Business Phone Entrypoint',
                'destination_type' => 'flow',
                'destination_id' => $flow->id,
                'is_active' => true,
            ]);
        }

        return $flow->fresh(['activeVersion']);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function starterFlowDefinition(Schedule $schedule): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'starter-start',
                    'type' => 'start',
                    'name' => 'Start',
                    'config' => [],
                ],
                [
                    'id' => 'starter-business-hours',
                    'type' => 'schedule_check',
                    'name' => 'Business Hours Check',
                    'config' => [
                        'schedule_id' => $schedule->id,
                    ],
                ],
                [
                    'id' => 'starter-open',
                    'type' => 'voicemail',
                    'name' => 'Main Daytime Voicemail',
                    'config' => [
                        'mailbox' => 'main',
                        'extension' => 'main',
                    ],
                ],
                [
                    'id' => 'starter-after-hours',
                    'type' => 'voicemail',
                    'name' => 'After Hours Voicemail',
                    'config' => [
                        'mailbox' => 'main',
                        'extension' => 'main',
                    ],
                ],
                [
                    'id' => 'starter-complete',
                    'type' => 'hangup',
                    'name' => 'Complete',
                    'config' => [],
                ],
            ],
            'edges' => [
                [
                    'source_node_id' => 'starter-start',
                    'target_node_id' => 'starter-business-hours',
                    'condition' => 'next',
                ],
                [
                    'source_node_id' => 'starter-business-hours',
                    'target_node_id' => 'starter-open',
                    'condition' => 'open',
                ],
                [
                    'source_node_id' => 'starter-business-hours',
                    'target_node_id' => 'starter-after-hours',
                    'condition' => 'closed',
                ],
                [
                    'source_node_id' => 'starter-business-hours',
                    'target_node_id' => 'starter-after-hours',
                    'condition' => 'break',
                ],
                [
                    'source_node_id' => 'starter-after-hours',
                    'target_node_id' => 'starter-complete',
                    'condition' => 'completed',
                ],
                [
                    'source_node_id' => 'starter-after-hours',
                    'target_node_id' => 'starter-complete',
                    'condition' => 'skipped',
                ],
            ],
        ];
    }

    protected function starterEntrypointNumber(Organization $organization): string
    {
        $numericSeed = sprintf('%010u', crc32((string) $organization->id));

        return '+1999'.$numericSeed;
    }
}
