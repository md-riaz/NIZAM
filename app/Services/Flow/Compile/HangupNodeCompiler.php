<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Services\Flow\Compile\Contracts\NodeCompiler;

class HangupNodeCompiler implements NodeCompiler
{
    public function compile(FlowNode $node, array $outgoingEdges): array
    {
        $config = $node->config ?? [];
        
        $instruction = IrInstruction::make('Hangup', [
            'node_id' => $node->id,
            'node_type' => 'hangup',
            'config' => $config,
        ]);

        $instruction->withLabel("node_{$node->id}");

        return [$instruction];
    }

    public function nodeType(): string
    {
        return 'hangup';
    }
}
