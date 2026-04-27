<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Services\Flow\Compile\Contracts\NodeCompiler;

class MenuNodeCompiler implements NodeCompiler
{
    public function compile(FlowNode $node, array $outgoingEdges): array
    {
        $config = $node->config_json ?? [];

        if (! isset($config['prompt']) && isset($config['greeting'])) {
            $config['prompt'] = $config['greeting'];
        }

        if (! isset($config['digits']) && isset($config['options']) && is_array($config['options'])) {
            $config['digits'] = array_values(array_filter(array_map(
                static fn ($option) => isset($option['digit']) ? trim((string) $option['digit']) : null,
                $config['options'],
            )));
        }

        $transitions = [];
        foreach ($outgoingEdges as $edge) {
            $result = $edge->condition ?? 'timeout';
            $transitions[$result] = "node_{$edge->target_node_id}";
        }

        $instruction = IrInstruction::make('CollectDigits', [
            'node_id' => $node->id,
            'node_type' => 'menu',
            'config' => $config,
            'transitions' => $transitions,
        ]);

        foreach ($transitions as $result => $targetLabel) {
            $instruction->withTransition($result, $targetLabel);
        }

        $instruction->withLabel("node_{$node->id}");

        return [$instruction];
    }

    public function nodeType(): string
    {
        return 'menu';
    }
}