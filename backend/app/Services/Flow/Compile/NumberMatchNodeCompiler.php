<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Models\FlowNode;
use App\Services\Flow\Compile\Contracts\NodeCompiler;

class NumberMatchNodeCompiler implements NodeCompiler
{
    public function compile(FlowNode $node, array $outgoingEdges): array
    {
        $config = $node->config_json ?? [];
        $transitions = [];

        foreach ($outgoingEdges as $edge) {
            $result = $edge->condition ?? 'no_match';
            $transitions[$result] = 'node_'.$edge->target_node_id;
        }

        $instruction = IrInstruction::make('MatchNumber', [
            'node_id' => $node->id,
            'node_type' => 'number_match',
            'config' => $config,
            'transitions' => $transitions,
        ]);

        foreach ($transitions as $result => $targetLabel) {
            $instruction->withTransition($result, $targetLabel);
        }

        $instruction->withLabel('node_'.$node->id);

        return [$instruction];
    }

    public function nodeType(): string
    {
        return 'number_match';
    }
}
