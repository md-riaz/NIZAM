<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;
use App\Services\Media\MediaControlService;

class HangupNodeHandler implements NodeHandler
{
    public function __construct(
        protected MediaControlService $mediaControlService,
    ) {}

    public function execute(array $node, CallContext $context): NodeResult
    {
        $cause = (string) data_get($node, 'config.cause', 'NORMAL_CLEARING');
        $this->mediaControlService->hangup($context->callSession, $cause);

        return NodeResult::complete([
            'message' => 'Flow completed at hangup node.',
            'cause' => $cause,
        ]);
    }
}
