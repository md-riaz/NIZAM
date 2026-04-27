<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Models\FlowNode;
use App\Services\Flow\Compile\Contracts\NodeCompiler;

class PlayMessageNodeCompiler implements NodeCompiler
{
    public function compile(FlowNode $node, array $outgoingEdges): array
    {
        $config = $node->config_json ?? [];

        if (! isset($config['prompt']) && isset($config['message'])) {
            $config['prompt'] = $config['message'];
        }

        $instruction = IrInstruction::make('PlayMessage', [
            'node_id' => $node->id,
            'node_type' => 'play_message',
            'config' => $config,
            'destination_type' => $config['destination_type'] ?? null,
            'destination_value' => $config['destination_value'] ?? null,
        ]);

        foreach ($outgoingEdges as $edge) {
            $instruction->withTransition($edge->condition ?? 'next', "node_{$edge->target_node_id}");
        }

        $instruction->withLabel("node_{$node->id}");

        return [$instruction];
    }

    public function nodeType(): string
    {
        return 'play_message';
    }
}
