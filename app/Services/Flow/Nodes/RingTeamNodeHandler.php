<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;
use App\Services\Media\MediaControlService;

class RingTeamNodeHandler implements NodeHandler
{
    public function __construct(
        protected MediaControlService $mediaControlService,
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

        if ($teamId) {
            $this->mediaControlService->ringTeam($context->callSession, (string) $teamId, $timeout);
        }

        return NodeResult::wait('call.answered', [
            'team_id' => $teamId,
            'timeout' => $timeout,
        ]);
    }
}
