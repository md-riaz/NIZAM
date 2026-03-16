<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Services\Flow\Compile\Contracts\NodeCompiler;

/**
 * Compiles start nodes into IR.
 *
 * Start nodes: answer (if needed) + transfer to first real node.
 */
class StartNodeCompiler implements NodeCompiler
{
    public function compile(FlowNode $node, array $outgoingEdges): array
    {
        $instructions = [];

        // Find the next node from edges
        $nextNodeId = null;
        foreach ($outgoingEdges as $edge) {
            if ($edge->condition === 'next' || $edge->condition === null) {
                $nextNodeId = $edge->target_node_id;
                break;
            }
        }

        // Create IR instruction
        $instruction = IrInstruction::make('AnswerAndTransfer', [
            'node_id' => $node->id,
            'node_type' => 'start',
            'config' => $node->config_json ?? [],
            'next_node_id' => $nextNodeId,
        ]);

        if ($nextNodeId) {
            $instruction->withTransition('next', "node_{$nextNodeId}");
        }

        $instruction->withLabel("node_{$node->id}");

        $instructions[] = $instruction;

        return $instructions;
    }

    public function nodeType(): string
    {
        return 'start';
    }
}
