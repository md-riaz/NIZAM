<?php

namespace App\Services\Flow\Compile\Contracts;

use App\Models\FlowNode;
use App\Models\FlowEdge;

/**
 * Contract for compiling a flow node into IR instructions.
 */
interface NodeCompiler
{
    /**
     * Compile a node into IR instructions.
     *
     * @param FlowNode $node The node to compile
     * @param FlowEdge[] $outgoingEdges Edges from this node
     * @return array<IrInstruction> Generated IR instructions
     */
    public function compile(FlowNode $node, array $outgoingEdges): array;

    /**
     * Get the node type this compiler handles.
     */
    public function nodeType(): string;
}
