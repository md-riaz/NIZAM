<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;
use App\Models\Team;
use App\Services\Media\MediaControlService;
use App\Services\Team\TeamRoutingService;

class RingTeamNodeHandler implements NodeHandler
{
    public function __construct(
        protected MediaControlService $mediaControlService,
        protected TeamRoutingService $teamRoutingService,
    ) {}

    public function execute(array $node, CallContext $context): NodeResult
    {
        $teamId = data_get($node, 'config.team_id');
        $answered = $context->variable('team_answered');

        if ($answered === true) {
            return NodeResult::transition('answered', [
                'team_id' => $teamId,
            ]);
        }

        if ($answered === false) {
            return NodeResult::transition('timeout', [
                'team_id' => $teamId,
            ]);
        }

        $timeout = (int) data_get($node, 'config.timeout', 20);
        $strategy = data_get($node, 'config.strategy');
        $team = $teamId
            ? Team::query()->where('organization_id', $context->organizationId())->whereKey($teamId)->where('is_active', true)->first()
            : null;
        $members = $team ? $this->teamRoutingService->resolveMembers($team, is_string($strategy) ? $strategy : null) : [];

        if ($team) {
            $this->mediaControlService->ringTeam($context->callSession, (string) $teamId, $timeout, $members);
        }

        return NodeResult::wait('call.answered', [
            'team_id' => $teamId,
            'timeout' => $timeout,
            'members' => $members,
        ]);
    }
}
