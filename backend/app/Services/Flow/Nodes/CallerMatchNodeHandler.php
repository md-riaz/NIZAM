<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;

class CallerMatchNodeHandler implements NodeHandler
{
    public function execute(array $node, CallContext $context): NodeResult
    {
        $config = (array) ($node['config'] ?? []);
        $mode = (string) ($config['mode'] ?? 'exact');
        $caller = (string) $context->variable('caller_number', '');

        $matched = match ($mode) {
            'anonymous' => $caller === '' || strtolower($caller) === 'anonymous',
            'prefix' => collect((array) ($config['numbers'] ?? []))
                ->contains(fn ($prefix) => $prefix !== '' && str_starts_with($caller, (string) $prefix)),
            'vip_list' => in_array($caller, (array) ($config['numbers'] ?? []), true),
            default => in_array($caller, (array) ($config['numbers'] ?? []), true),
        };

        return NodeResult::transition($matched ? 'match' : 'no_match', [
            'mode' => $mode,
            'caller_number' => $caller,
        ]);
    }
}
