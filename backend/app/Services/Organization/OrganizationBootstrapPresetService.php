<?php

namespace App\Services\Organization;

use App\Models\Schedule;

class OrganizationBootstrapPresetService
{
    /**
     * @return array<string, mixed>
     */
    public function defaultPreset(Schedule $schedule, string $openTargetType, string $openTargetId, ?string $operatorExtension = null): array
    {
        return [
            'flow' => [
                'name' => 'Main Business Phone',
                'description' => 'Starter business-hours entrypoint with after-hours voicemail fallback.',
                'definition' => $this->starterFlowDefinition($schedule, $openTargetType, $openTargetId),
            ],
            'default_entrypoint' => array_filter([
                'type' => 'flow',
                'schedule_id' => (string) $schedule->id,
                'open_target_type' => $openTargetType,
                'open_target_id' => $openTargetId,
                'operator_extension' => $operatorExtension,
                'provisioned' => true,
            ], static fn ($value): bool => $value !== null),
            'office_features' => $this->normalizeOfficeFeatures(),
        ];
    }

    /**
     * @param  array<string, mixed>  $features
     * @return array<string, bool>
     */
    public function normalizeOfficeFeatures(array $features = []): array
    {
        return [
            'parking_enabled' => (bool) ($features['parking_enabled'] ?? false),
            'pickup_enabled' => (bool) ($features['pickup_enabled'] ?? false),
            'paging_enabled' => (bool) ($features['paging_enabled'] ?? false),
            'intercom_enabled' => (bool) ($features['intercom_enabled'] ?? false),
            'directory_enabled' => (bool) ($features['directory_enabled'] ?? false),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function starterFlowDefinition(Schedule $schedule, string $openTargetType, string $openTargetId): array
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
                    'type' => $openTargetType === 'team' ? 'ring_team' : 'play_message',
                    'name' => $openTargetType === 'team' ? 'Main Team' : 'Main Extension',
                    'config' => $openTargetType === 'team'
                        ? [
                            'team_id' => $openTargetId,
                            'timeout' => 20,
                        ]
                        : [
                            'prompt' => 'Routing to main extension.',
                            'destination_type' => $openTargetType,
                            'destination_value' => $openTargetId,
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
                    'source_node_id' => 'starter-business-hours',
                    'target_node_id' => 'starter-after-hours',
                    'condition' => 'holiday',
                ],
                [
                    'source_node_id' => 'starter-open',
                    'target_node_id' => 'starter-complete',
                    'condition' => $openTargetType === 'team' ? 'no_answer' : 'next',
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
}
