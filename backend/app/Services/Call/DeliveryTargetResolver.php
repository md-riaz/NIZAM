<?php

namespace App\Services\Call;

use App\Models\Agent;
use App\Models\CallSession;
use App\Models\Extension;
use App\Models\FlowNode;
use App\Models\Queue;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Organization;
use App\Models\TimeCondition;
use App\Services\QueueService;
use App\Services\Schedule\ScheduleEngine;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class DeliveryTargetResolver
{
    public const HUMAN_TARGET_TYPES = [
        'extension',
        'queue',
        'agent',
        'did',
        'time_condition',
        'flow',
        'team',
    ];

    public const CANONICAL_TARGET_TYPES = [
        'extension',
        'agent',
    ];

    public function __construct(
        protected QueueService $queueService,
        protected ScheduleEngine $scheduleEngine,
    ) {}

    public function resolve(CallSession $callSession, ?DateTimeInterface $evaluatedAt = null): DeliveryTargetSet
    {
        $variables = $callSession->variables ?? [];
        $targetType = (string) data_get($variables, 'nizam_delivery_target_type', '');
        $targetId = (string) data_get($variables, 'nizam_delivery_target_id', '');

        if ($targetType === '' || $targetId === '') {
            throw new InvalidArgumentException('Call session is missing delivery target metadata.');
        }

        return $this->resolveTarget(
            organization: $callSession->organization,
            targetType: $targetType,
            targetId: $targetId,
            sourcePath: [[
                'type' => $targetType,
                'id' => $targetId,
                'origin' => 'call_session',
            ]],
            evaluatedAt: $evaluatedAt,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    protected function resolveTarget(
        Organization $organization,
        string $targetType,
        string $targetId,
        array $sourcePath,
        ?DateTimeInterface $evaluatedAt = null,
    ): DeliveryTargetSet {
        return match ($targetType) {
            'extension' => $this->resolveExtension($organization, $targetId, $sourcePath),
            'queue' => $this->resolveQueue($organization, $targetId, $sourcePath),
            'agent' => $this->resolveAgent($organization, $targetId, $sourcePath),
            'did' => $this->resolveDid($organization, $targetId, $sourcePath, $evaluatedAt),
            'time_condition' => $this->resolveTimeCondition($organization, $targetId, $sourcePath, $evaluatedAt),
            'flow' => $this->resolveFlow($organization, $targetId, $sourcePath, $evaluatedAt),
            'team' => $this->resolveTeam($organization, $targetId, $sourcePath),
            default => $this->emptySet($sourcePath, [
                'final_target_type' => $targetType,
                'final_target_id' => $targetId,
                'bypass_reason' => 'unsupported_target_type',
            ]),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    protected function resolveExtension(Organization $organization, string $extensionId, array $sourcePath): DeliveryTargetSet
    {
        $extension = $organization->extensions()
            ->whereKey($extensionId)
            ->where('is_active', true)
            ->first();

        if (! $extension) {
            return $this->emptySet($sourcePath, ['bypass_reason' => 'extension_not_found']);
        }

        return new DeliveryTargetSet(
            targets: [new DeliveryTarget(
                type: 'extension',
                id: $extension->id,
                sourcePath: $sourcePath,
                metadata: [
                    'extension' => $extension->extension,
                    'agent_id' => $extension->agent?->id,
                ],
            )],
            sourcePath: $sourcePath,
            metadata: [
                'resolved_from' => 'extension',
                'final_target_type' => 'extension',
                'final_target_id' => $extension->id,
            ],
        );
    }



    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    protected function resolveQueue(Organization $organization, string $queueId, array $sourcePath): DeliveryTargetSet
    {
        $queue = $organization->queues()
            ->whereKey($queueId)
            ->where('is_active', true)
            ->first();

        if (! $queue) {
            return $this->emptySet($sourcePath, ['bypass_reason' => 'queue_not_found']);
        }

        $eligibleAgents = match ($queue->strategy) {
            Queue::STRATEGY_RING_ALL => $this->queueService->getAgentsForRingAll($queue),
            default => collect([$this->queueService->selectAgent($queue)])->filter(),
        };

        if ($eligibleAgents->isEmpty()) {
            return $this->emptySet($sourcePath, [
                'bypass_reason' => 'queue_without_eligible_agents',
                'queue_id' => $queue->id,
                'strategy' => $queue->strategy,
            ]);
        }

        $targets = $eligibleAgents->values()->map(fn (Agent $agent, int $index) => new DeliveryTarget(
            type: 'agent',
            id: $agent->id,
            sourcePath: $this->appendSourcePath($sourcePath, [
                'type' => 'queue',
                'id' => $queue->id,
                'strategy' => $queue->strategy,
                'queue_position' => $index,
                'agent_id' => $agent->id,
            ]),
            metadata: [
                'queue_id' => $queue->id,
                'queue_strategy' => $queue->strategy,
                'extension_id' => $agent->extension_id,
            ],
        ))->all();

        return new DeliveryTargetSet(
            targets: $targets,
            sourcePath: $sourcePath,
            metadata: [
                'resolved_from' => 'queue',
                'queue_id' => $queue->id,
                'strategy' => $queue->strategy,
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    protected function resolveAgent(Organization $organization, string $agentId, array $sourcePath): DeliveryTargetSet
    {
        $agent = $organization->agents()
            ->whereKey($agentId)
            ->where('is_active', true)
            ->first();

        if (! $agent) {
            return $this->emptySet($sourcePath, ['bypass_reason' => 'agent_not_found']);
        }

        return new DeliveryTargetSet(
            targets: [new DeliveryTarget(
                type: 'agent',
                id: $agent->id,
                sourcePath: $sourcePath,
                metadata: [
                    'extension_id' => $agent->extension_id,
                    'agent_state' => $agent->state,
                ],
            )],
            sourcePath: $sourcePath,
            metadata: [
                'resolved_from' => 'agent',
                'final_target_type' => 'agent',
                'final_target_id' => $agent->id,
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    protected function resolveDid(Organization $organization, string $didId, array $sourcePath, ?DateTimeInterface $evaluatedAt = null): DeliveryTargetSet
    {
        $did = $organization->dids()
            ->whereKey($didId)
            ->where('is_active', true)
            ->first();

        if (! $did) {
            return $this->emptySet($sourcePath, ['bypass_reason' => 'did_not_found']);
        }

        if (! $this->isHumanTargetType($did->destination_type)) {
            return $this->emptySet($sourcePath, [
                'bypass_reason' => 'non_human_destination',
                'destination_type' => $did->destination_type,
                'destination_id' => $did->destination_id,
            ]);
        }

        return $this->resolveTarget(
            organization: $organization,
            targetType: (string) $did->destination_type,
            targetId: (string) $did->destination_id,
            sourcePath: $this->appendSourcePath($sourcePath, [
                'type' => 'did',
                'id' => $did->id,
                'number' => $did->number,
                'destination_type' => $did->destination_type,
                'destination_id' => $did->destination_id,
            ]),
            evaluatedAt: $evaluatedAt,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    protected function resolveTimeCondition(Organization $organization, string $timeConditionId, array $sourcePath, ?DateTimeInterface $evaluatedAt = null): DeliveryTargetSet
    {
        $timeCondition = $organization->timeConditions()
            ->whereKey($timeConditionId)
            ->where('is_active', true)
            ->first();

        if (! $timeCondition) {
            return $this->emptySet($sourcePath, ['bypass_reason' => 'time_condition_not_found']);
        }

        $matched = $this->timeConditionMatches($timeCondition, $evaluatedAt);
        $branch = $matched ? 'match' : 'no_match';
        $destinationType = (string) ($matched ? $timeCondition->match_destination_type : $timeCondition->no_match_destination_type);
        $destinationId = (string) ($matched ? $timeCondition->match_destination_id : $timeCondition->no_match_destination_id);

        if ($destinationType === '' || $destinationId === '') {
            return $this->emptySet($sourcePath, [
                'bypass_reason' => 'time_condition_branch_missing_destination',
                'branch' => $branch,
            ]);
        }

        if (! $this->isHumanTargetType($destinationType)) {
            return $this->emptySet($sourcePath, [
                'bypass_reason' => 'non_human_destination',
                'branch' => $branch,
                'destination_type' => $destinationType,
                'destination_id' => $destinationId,
            ]);
        }

        return $this->resolveTarget(
            organization: $organization,
            targetType: $destinationType,
            targetId: $destinationId,
            sourcePath: $this->appendSourcePath($sourcePath, [
                'type' => 'time_condition',
                'id' => $timeCondition->id,
                'branch' => $branch,
                'evaluated_at' => $this->evaluationMoment($evaluatedAt)->toIso8601String(),
            ]),
            evaluatedAt: $evaluatedAt,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    protected function resolveFlow(Organization $organization, string $flowId, array $sourcePath, ?DateTimeInterface $evaluatedAt = null): DeliveryTargetSet
    {
        $flow = $organization->flows()
            ->whereKey($flowId)
            ->first();

        if (! $flow || ! $flow->activeVersion) {
            return $this->emptySet($sourcePath, ['bypass_reason' => 'flow_not_found_or_unpublished']);
        }

        $flow->loadMissing('activeVersion.nodes.outgoingEdges.targetNode');

        $startNode = $flow->activeVersion->nodes->firstWhere('type', 'start');

        if (! $startNode) {
            return $this->emptySet($sourcePath, ['bypass_reason' => 'flow_without_start_node']);
        }

        $currentNode = $this->firstConnectedNode($startNode);

        while ($currentNode) {
            $nodePath = [
                'type' => 'flow',
                'id' => $flow->id,
                'flow_version_id' => $flow->activeVersion->id,
                'node_id' => $currentNode->id,
                'node_type' => $currentNode->type,
            ];

            if ($currentNode->type === 'ring_team') {
                $teamId = (string) data_get($currentNode->config_json, 'team_id', '');

                if ($teamId === '') {
                    return $this->emptySet($sourcePath, ['bypass_reason' => 'flow_ring_team_missing_team']);
                }

                return $this->resolveTarget(
                    organization: $organization,
                    targetType: 'team',
                    targetId: $teamId,
                    sourcePath: $this->appendSourcePath($sourcePath, $nodePath),
                );
            }

            if (in_array($currentNode->type, ['voicemail', 'hangup', 'menu'], true)) {
                return $this->emptySet($sourcePath, [
                    'bypass_reason' => 'flow_branch_not_human_target',
                    'node_id' => $currentNode->id,
                    'node_type' => $currentNode->type,
                ]);
            }

            if (in_array($currentNode->type, ['schedule_check', 'business_hours'], true)) {
                [$branch, $nextNode] = $this->resolveFlowScheduleBranch($organization, $currentNode, $evaluatedAt);

                if (! $nextNode) {
                    return $this->emptySet($sourcePath, [
                        'bypass_reason' => 'flow_schedule_branch_missing_target',
                        'node_id' => $currentNode->id,
                        'branch' => $branch,
                    ]);
                }

                $currentNode = $nextNode;
                $sourcePath = $this->appendSourcePath($sourcePath, $nodePath + [
                    'branch' => $branch,
                    'evaluated_at' => $this->evaluationMoment($evaluatedAt)->toIso8601String(),
                ]);

                continue;
            }

            $currentNode = $this->firstConnectedNode($currentNode);
        }

        return $this->emptySet($sourcePath, ['bypass_reason' => 'flow_without_human_target_branch']);
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    protected function resolveTeam(Organization $organization, string $teamId, array $sourcePath): DeliveryTargetSet
    {
        $team = $organization->teams()
            ->whereKey($teamId)
            ->where('is_active', true)
            ->first();

        if (! $team) {
            return $this->emptySet($sourcePath, ['bypass_reason' => 'team_not_found']);
        }

        $members = $team->members()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        $targets = [];

        foreach ($members as $member) {
            $target = $this->teamMemberToTarget($member, $team, $sourcePath);

            if ($target) {
                $targets[] = $target;
            }
        }

        if ($targets === []) {
            return $this->emptySet($sourcePath, [
                'bypass_reason' => 'team_without_human_members',
                'team_id' => $team->id,
            ]);
        }

        return new DeliveryTargetSet(
            targets: $targets,
            sourcePath: $sourcePath,
            metadata: [
                'resolved_from' => 'team',
                'team_id' => $team->id,
                'strategy' => $team->strategy,
            ],
        );
    }

    protected function firstConnectedNode(FlowNode $node): ?FlowNode
    {
        $edge = $node->outgoingEdges->sortBy('condition')->first();

        return $edge?->targetNode;
    }

    /**
     * @return array{0: string, 1: ?FlowNode}
     */
    protected function resolveFlowScheduleBranch(Organization $organization, FlowNode $node, ?DateTimeInterface $evaluatedAt = null): array
    {
        $scheduleId = (string) data_get($node->config_json, 'schedule_id', '');
        $branch = 'default';

        if ($scheduleId !== '') {
            $schedule = $organization->schedules()
                ->whereKey($scheduleId)
                ->where('is_active', true)
                ->first();

            if ($schedule) {
                $branch = $this->scheduleEngine->evaluate($schedule, $evaluatedAt);
            }
        }

        $edge = $node->outgoingEdges->firstWhere('condition', $branch)
            ?? $node->outgoingEdges->firstWhere('condition', 'default');

        return [$branch, $edge?->targetNode];
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     */
    protected function teamMemberToTarget(TeamMember $member, Team $team, array $sourcePath): ?DeliveryTarget
    {
        $targetType = match ($member->endpoint_type) {
            'extension', Extension::class => 'extension',
            'agent', Agent::class => 'agent',
            default => null,
        };

        if (! $targetType) {
            return null;
        }

        if ($targetType === 'extension') {
            $extension = $team->organization->extensions()
                ->whereKey($member->endpoint_id)
                ->where('is_active', true)
                ->first();

            if (! $extension) {
                return null;
            }
        }

        if ($targetType === 'agent') {
            $agent = $team->organization->agents()
                ->whereKey($member->endpoint_id)
                ->where('is_active', true)
                ->first();

            if (! $agent || ! $agent->extension?->is_active) {
                return null;
            }
        }

        return new DeliveryTarget(
            type: $targetType,
            id: (string) $member->endpoint_id,
            sourcePath: $this->appendSourcePath($sourcePath, [
                'type' => 'team',
                'id' => $team->id,
                'strategy' => $team->strategy,
                'member_id' => $member->id,
                'member_endpoint_type' => $targetType,
                'member_endpoint_id' => $member->endpoint_id,
                'priority' => $member->priority,
            ]),
            metadata: [
                'team_id' => $team->id,
                'team_strategy' => $team->strategy,
                'priority' => $member->priority,
            ],
        );
    }

    protected function isHumanTargetType(?string $targetType): bool
    {
        return in_array($targetType, self::HUMAN_TARGET_TYPES, true);
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     * @param  array<string, mixed>  $segment
     * @return list<array<string, mixed>>
     */
    protected function appendSourcePath(array $sourcePath, array $segment): array
    {
        $sourcePath[] = $segment;

        return $sourcePath;
    }

    /**
     * @param  list<array<string, mixed>>  $sourcePath
     * @param  array<string, mixed>  $metadata
     */
    protected function emptySet(array $sourcePath, array $metadata = []): DeliveryTargetSet
    {
        return new DeliveryTargetSet([], $sourcePath, $metadata);
    }

    protected function timeConditionMatches(TimeCondition $timeCondition, ?DateTimeInterface $evaluatedAt = null): bool
    {
        $moment = $this->evaluationMoment($evaluatedAt);
        $conditions = $timeCondition->conditions ?? [];

        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            if ($this->conditionMatches($condition, $moment)) {
                return true;
            }
        }

        return false;
    }

    protected function evaluationMoment(?DateTimeInterface $evaluatedAt = null): CarbonImmutable
    {
        if (! $evaluatedAt) {
            return CarbonImmutable::now();
        }

        return CarbonImmutable::instance(new \DateTimeImmutable($evaluatedAt->format(DATE_ATOM)));
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    protected function conditionMatches(array $condition, CarbonImmutable $moment): bool
    {
        $weekday = (int) $moment->dayOfWeekIso;
        $time = $moment->format('H:i');

        if (filled($condition['wday'] ?? null) && ! $this->matchesDayExpression((string) $condition['wday'], $weekday)) {
            return false;
        }

        $timeFrom = $condition['time_from'] ?? null;
        $timeTo = $condition['time_to'] ?? null;

        if (filled($timeFrom) && filled($timeTo)) {
            return $time >= (string) $timeFrom && $time <= (string) $timeTo;
        }

        return true;
    }

    protected function matchesDayExpression(string $expression, int $weekday): bool
    {
        $aliases = [
            'mon' => 1,
            'tue' => 2,
            'wed' => 3,
            'thu' => 4,
            'fri' => 5,
            'sat' => 6,
            'sun' => 7,
        ];

        foreach (explode(',', strtolower($expression)) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (str_contains($part, '-')) {
                [$start, $end] = array_map('trim', explode('-', $part, 2));
                $startDay = $aliases[$start] ?? (int) $start;
                $endDay = $aliases[$end] ?? (int) $end;

                if ($weekday >= $startDay && $weekday <= $endDay) {
                    return true;
                }

                continue;
            }

            $day = $aliases[$part] ?? (int) $part;

            if ($day === $weekday) {
                return true;
            }
        }

        return false;
    }
}
