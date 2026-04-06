<?php

namespace App\Services\Flow;

use App\Models\FlowVersion;

class FlowDefinitionMapper
{
    public function toExecutionDefinition(FlowVersion $flowVersion): array
    {
        $nodes = $flowVersion->nodes
            ->map(fn ($node) => [
                'id' => $node->id,
                'type' => $node->type,
                'name' => $node->name,
                'config' => $node->config_json ?? [],
                'position_x' => $node->position_x,
                'position_y' => $node->position_y,
            ])
            ->values()
            ->all();

        $edges = $flowVersion->edges
            ->map(fn ($edge) => [
                'id' => $edge->id,
                'source_node_id' => $edge->source_node_id,
                'target_node_id' => $edge->target_node_id,
                'condition' => $edge->condition,
            ])
            ->values()
            ->all();

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
