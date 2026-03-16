<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Services\Flow\Compile\Contracts\NodeCompiler;

/**
 * Compiles schedule_check nodes into IR.
 *
 * Schedule check nodes: transfer to compiled schedule fragment,
 * set nizam_schedule_state, branch to target node extensions.
 */
class ScheduleCheckNodeCompiler implements NodeCompiler
{
    public function compile(FlowNode $node, array $outgoingEdges): array
    {
        $instructions = [];

        $config = $node->config_json ?? [];
        $scheduleId = $config['schedule_id'] ?? null;

        // Build transition map from edges
        $transitions = [];
        foreach ($outgoingEdges as $edge) {
            $result = $edge->condition ?? 'open';
            $transitions[$result] = "node_{$edge->target_node_id}";
        }

        $instruction = IrInstruction::make('CheckSchedule', [
            'node_id' => $node->id,
            'node_type' => 'schedule_check',
            'config' => $config,
            'schedule_id' => $scheduleId,
            'transitions' => $transitions,
        ]);

        // Add all valid transitions
        foreach ($transitions as $result => $targetLabel) {
            $instruction->withTransition($result, $targetLabel);
        }

        $instruction->withLabel("node_{$node->id}");

        $instructions[] = $instruction;

        return $instructions;
    }

    public function nodeType(): string
    {
        return 'schedule_check';
    }
}
