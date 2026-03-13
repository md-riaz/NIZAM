<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;

class StartNodeHandler implements NodeHandler
{
    public function execute(array $node, CallContext $context): NodeResult
    {
        return NodeResult::transition('next', [
            'message' => 'Start node executed.',
        ]);
    }
}
