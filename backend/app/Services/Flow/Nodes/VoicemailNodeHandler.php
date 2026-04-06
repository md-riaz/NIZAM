<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;

class VoicemailNodeHandler implements NodeHandler
{
    public function execute(array $node, CallContext $context): NodeResult
    {
        return NodeResult::complete([
            'message' => 'Voicemail node reached.',
            'mailbox' => data_get($node, 'config.mailbox'),
        ]);
    }
}
