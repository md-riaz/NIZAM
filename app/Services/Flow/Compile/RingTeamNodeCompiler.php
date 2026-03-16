<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Services\Flow\Compile\Contracts\NodeCompiler;

class RingTeamNodeCompiler implements NodeCompiler
{
    public function compile(FlowNode $node, array $outgoingEdges): array
    {
        $config = $node->config ?? [];
        $teamId = $config['team_id'] ?? null;
        $timeout = $config['timeout'] ?? 30;
        
        $transitions = [];
        foreach ($outgoingEdges as $edge) {
            $result = $edge->transition_result ?? 'no_answer';
            $transitions[$result] = "node_{$edge->target_node_id}";
        }

        $instruction = IrInstruction::make('BridgeTeam', [
            'node_id' => $node->id,
            'node_type' => 'ring_team',
            'config' => $config,
            'team_id' => $teamId,
            'timeout' => $timeout,
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
        return 'ring_team';
    }
}
