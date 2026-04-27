<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;

class NumberMatchNodeHandler implements NodeHandler
{
    public function execute(array $node, CallContext $context): NodeResult
    {
        $config = (array) ($node['config'] ?? []);
        $mode = (string) ($config['mode'] ?? 'did');
        $calledNumber = (string) $context->variable('called_number', $context->variable('did_number', ''));

        $matched = match ($mode) {
            'number_group' => in_array($calledNumber, (array) ($config['numbers'] ?? []), true),
            default => in_array($calledNumber, (array) ($config['numbers'] ?? []), true),
        };

        return NodeResult::transition($matched ? 'match' : 'no_match', [
            'mode' => $mode,
            'called_number' => $calledNumber,
        ]);
    }
}
