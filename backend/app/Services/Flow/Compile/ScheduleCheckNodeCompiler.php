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

        $transitions = [];
        foreach ($outgoingEdges as $edge) {
            $result = $edge->condition ?? 'closed';
            $transitions[$result] = "node_{$edge->target_node_id}";
        }

        if (! isset($transitions['holiday']) && isset($transitions['closed'])) {
            $transitions['holiday'] = $transitions['closed'];
        }

        if (! isset($transitions['closed']) && isset($transitions['holiday'])) {
            $transitions['closed'] = $transitions['holiday'];
        }

        if (! isset($transitions['open'])) {
            $transitions['open'] = $transitions['closed'] ?? $transitions['holiday'] ?? null;
        }

        $transitions = array_filter($transitions);

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
