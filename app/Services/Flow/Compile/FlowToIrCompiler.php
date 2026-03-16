<?php

namespace App\Services\Flow\Compile;

use App\Domain\Flow\Compile\IrInstruction;
use App\Domain\Flow\Compile\NodeSpecRegistry;
use App\Models\FlowVersion;
use App\Services\Flow\Compile\Contracts\NodeCompiler;
use RuntimeException;

/**
 * Compiles a FlowVersion graph into IR instructions.
 *
 * This is the first stage of compilation:
 * Graph (nodes/edges) -> IR Instructions -> XML/Lua generation
 */
class FlowToIrCompiler
{
    /**
     * @var array<string, NodeCompiler>
     */
    protected array $nodeCompilers = [];

    public function __construct(
        protected NodeSpecRegistry $nodeSpecRegistry,
    ) {
        $this->registerCompilers([
            new StartNodeCompiler(),
            new ScheduleCheckNodeCompiler(),
            new MenuNodeCompiler(),
            new VoicemailNodeCompiler(),
            new HangupNodeCompiler(),
        ]);
    }

    public function registerCompilers(array $compilers): void
    {
        foreach ($compilers as $compiler) {
            $this->nodeCompilers[$compiler->nodeType()] = $compiler;
        }
    }

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

            $nodeEdges = $edgesBySource[$node->id] ?? collect([]);
            // Convert to array of FlowEdge models
            $nodeEdgesArray = $nodeEdges->all();

            $compiler = $this->nodeCompilers[$node->node_type] ?? null;

            if (!$compiler) {
                // Fallback or just throw if we want strictly modular
                // throw new RuntimeException("No NodeCompiler registered for type: {$node->node_type}");
                // For now, let's throw to enforce modular compilation
                throw new RuntimeException("No NodeCompiler registered for type: {$node->node_type}");
            }

            $nodeInstructions = $compiler->compile($node, $nodeEdgesArray);
            
            foreach ($nodeInstructions as $instruction) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
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
            if (!isset($this->nodeCompilers[$node->node_type])) {
                return false;
            }
        }

        return true;
    }
}
