<?php

namespace App\Services\Flow;

use App\Domain\Flow\Compile\NodeSpecRegistry;
use App\Models\FlowVersion;

class FlowIntegrityValidator
{
    public function __construct(
        protected NodeSpecRegistry $nodeSpecRegistry,
    ) {}

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
            $canonicalType = $this->nodeSpecRegistry->canonicalType($node->type) ?? $node->type;
            $outgoingEdges = $edges->where('source_node_id', $node->id)->values();

            if ($canonicalType !== 'start') {
                $incomingCount = $edges->where('target_node_id', $node->id)->count();

                if ($incomingCount === 0) {
                    $errors[] = "Node {$node->id} [{$node->type}] is unreachable.";
                }
            }

            if (in_array($canonicalType, ['hangup'], true) && $outgoingEdges->isNotEmpty()) {
                $errors[] = "Node {$node->id} [{$node->type}] cannot have outgoing edges.";
            }

            foreach ($this->requiredBranchesFor($canonicalType) as $requiredBranch) {
                if (! $outgoingEdges->firstWhere('condition', $requiredBranch)) {
                    $errors[] = "Node {$node->id} [{$node->type}] is missing required [{$requiredBranch}] branch.";
                }
            }
        }

        return array_values(array_unique($errors));
    }

    protected function requiredBranchesFor(string $type): array
    {
        return match ($type) {
            'schedule_check' => ['open', 'closed', 'holiday'],
            'caller_match', 'number_match' => ['match', 'no_match'],
            default => [],
        };
    }
}
