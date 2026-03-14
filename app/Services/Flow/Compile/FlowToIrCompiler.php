<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Domain\Flow\Compile\NodeSpecRegistry;
use App\Models\FlowVersion;
use RuntimeException;

/**
 * Compiles a FlowVersion graph into IR instructions.
 *
 * This is the first stage of compilation:
 * Graph (nodes/edges) -> IR Instructions -> XML/Lua generation
 */
class FlowToIrCompiler
{
    public function __construct(
        protected NodeSpecRegistry $nodeSpecRegistry,
    ) {}

    /**
     * Compile a flow version into a list of IR instructions.
     *
     * @return IrInstruction[]
     */
    public function compile(FlowVersion $flowVersion): array
    {
        $flowVersion->load(['nodes', 'edges']);

        $nodes = $flowVersion->nodes->keyBy('id');
        $edgesBySource = $flowVersion->edges->groupBy('source_node_id');

        $instructions = [];

        foreach ($flowVersion->nodes as $node) {
            $spec = $this->nodeSpecRegistry->get($node->node_type);

            if (!$spec) {
                throw new RuntimeException("Unknown node type: {$node->node_type}");
            }

            $nodeEdges = $edgesBySource[$node->id] ?? [];

            $instruction = $this->compileNode($node, $spec, $nodeEdges, $nodes);

            if ($instruction) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
    }

    /**
     * Compile a single node into an IR instruction.
     */
    protected function compileNode(
        object $node,
        object $spec,
        array $edges,
        object $nodes
    ): ?IrInstruction {
        $instruction = IrInstruction::make($spec->irType, [
            'node_id' => $node->id,
            'node_type' => $node->node_type,
            'config' => $node->config ?? [],
        ]);

        // Build transitions from edges
        foreach ($edges as $edge) {
            $targetNode = $nodes[$edge->target_node_id] ?? null;

            if (!$targetNode) {
                continue;
            }

            $transitionResult = $edge->transition_result ?? 'next';
            $targetLabel = "node_{$targetNode->id}";

            $instruction->withTransition($transitionResult, $targetLabel);
        }

        // Set label for this node
        $instruction->withLabel("node_{$node->id}");

        return $instruction;
    }

    /**
     * Validate that IR can be generated (pre-flight check).
     */
    public function canCompile(FlowVersion $flowVersion): bool
    {
        foreach ($flowVersion->nodes as $node) {
            if (!$this->nodeSpecRegistry->has($node->node_type)) {
                return false;
            }
        }

        return true;
    }
}
