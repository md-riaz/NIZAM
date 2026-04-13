<?php

namespace App\Services;

use App\Data\FlowData;
use App\Models\Did;
use App\Models\Flow;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Tenant;
use App\Services\Flow\FlowApplicationService;

class TenantEntrypointProvisioningService
{
    public function __construct(
        protected FlowApplicationService $flowApplicationService,
        protected TenantManifestBuilder $tenantManifestBuilder,
    ) {}

    public function provision(Tenant $tenant): Tenant
    {
        $schedule = $this->provisionDefaultSchedule($tenant);
        $flow = $this->provisionStarterMainFlow($tenant, $schedule);

        $settings = $tenant->settings ?? [];
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

        $tenant->forceFill([
            'default_schedule_id' => $schedule->id,
            'settings' => $settings,
        ])->save();

        if (! $tenant->relationLoaded('defaultSchedule')) {
            $tenant->loadMissing('defaultSchedule');
        }

        if (! $tenant->defaultSchedule?->holiday_calendar_id && $tenant->default_holiday_calendar_id) {
            $tenant->defaultSchedule->update([
                'holiday_calendar_id' => $tenant->default_holiday_calendar_id,
            ]);
        }

        $tenant->refresh();
        $schedule = $tenant->defaultSchedule ?? $schedule;

        $this->tenantManifestBuilder->buildAndActivate($tenant->fresh());

        return $tenant->fresh(['defaultSchedule', 'flows.activeVersion', 'dids']);
    }

    protected function provisionDefaultSchedule(Tenant $tenant): Schedule
    {
        if ($tenant->defaultSchedule) {
            return $tenant->defaultSchedule;
        }

        $schedule = $tenant->schedules()->create([
            'holiday_calendar_id' => $tenant->default_holiday_calendar_id,
            'name' => 'Main Business Hours',
            'timezone' => (string) data_get($tenant->settings, 'timezone', config('telephony.bootstrap.default_timezone', 'Asia/Dhaka')),
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

    protected function provisionStarterMainFlow(Tenant $tenant, Schedule $schedule): Flow
    {
        $existingFlowId = data_get($tenant->settings, 'business_phone.default_entrypoint.flow_id');

        if (is_string($existingFlowId) && $existingFlowId !== '') {
            $existingFlow = $tenant->flows()->find($existingFlowId);

            if ($existingFlow) {
                return $existingFlow;
            }
        }

        $flow = $this->flowApplicationService->create($tenant->id, new FlowData(
            name: 'Main Business Phone',
            description: 'Starter business-hours entrypoint with after-hours voicemail fallback.',
            definition: $this->starterFlowDefinition($schedule),
            publish: true,
        ));

        $did = $tenant->dids()->firstWhere('description', 'Default Business Phone Entrypoint');

        if (! $did) {
            $tenant->dids()->create([
                'number' => $this->starterEntrypointNumber($tenant),
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

    protected function starterEntrypointNumber(Tenant $tenant): string
    {
        $numericSeed = sprintf('%010u', crc32((string) $tenant->id));

        return '+1999'.$numericSeed;
    }
}
