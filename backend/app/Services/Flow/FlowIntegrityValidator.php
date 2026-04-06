<?php

namespace App\Services\Flow;

use App\Models\FlowVersion;

class FlowIntegrityValidator
{
    public function validate(FlowVersion $flowVersion): array
    {
        $flowVersion->loadMissing(['nodes', 'edges']);

        $errors = [];
        $nodes = $flowVersion->nodes;
        $edges = $flowVersion->edges;
        $nodeIds = $nodes->pluck('id')->all();
        $startNodes = $nodes->where('type', 'start');

        if ($nodes->isEmpty()) {
            $errors[] = 'Flow version has no nodes.';
        }

        if ($startNodes->count() !== 1) {
            $errors[] = 'Flow version must have exactly one start node.';
        }

        foreach ($edges as $edge) {
            if (! in_array($edge->source_node_id, $nodeIds, true)) {
                $errors[] = "Edge {$edge->id} has invalid source node.";
            }

            if (! in_array($edge->target_node_id, $nodeIds, true)) {
                $errors[] = "Edge {$edge->id} has invalid target node.";
            }
        }

        foreach ($nodes as $node) {
            if ($node->type === 'start') {
                continue;
            }

            $incomingCount = $edges->where('target_node_id', $node->id)->count();

            if ($incomingCount === 0) {
                $errors[] = "Node {$node->id} [{$node->type}] is unreachable.";
            }
        }

        return array_values(array_unique($errors));
    }
}
