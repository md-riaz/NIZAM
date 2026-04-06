<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Services\Flow\Compile\Contracts\NodeCompiler;

class VoicemailNodeCompiler implements NodeCompiler
{
    public function compile(FlowNode $node, array $outgoingEdges): array
    {
        $config = $node->config_json ?? [];
        
        $transitions = [];
        foreach ($outgoingEdges as $edge) {
            $result = $edge->condition ?? 'completed';
            $transitions[$result] = "node_{$edge->target_node_id}";
        }

        $instruction = IrInstruction::make('Voicemail', [
            'node_id' => $node->id,
            'node_type' => 'voicemail',
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
        return 'voicemail';
    }
}
