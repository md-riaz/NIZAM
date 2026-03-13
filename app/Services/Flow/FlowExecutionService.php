<?php

namespace App\Services\Flow;

use App\Domain\Flow\CallContext;
use App\Models\CallSession;
use App\Services\Call\TraceWriter;
use RuntimeException;

class FlowExecutionService
{
    public function __construct(
        protected NodeHandlerFactory $nodeHandlerFactory,
        protected EdgeResolver $edgeResolver,
        protected TraceWriter $traceWriter,
    ) {}

    public function executeArrayDefinition(CallSession $callSession, array $definition): array
    {
        $nodes = $definition['nodes'] ?? [];
        $edges = $definition['edges'] ?? [];

        if ($nodes === []) {
            throw new RuntimeException('Flow definition has no nodes.');
        }

        $indexedNodes = collect($nodes)->keyBy(fn (array $node) => $node['id'] ?? null)->all();
        $startNode = collect($nodes)->first(fn (array $node) => ($node['type'] ?? null) === 'start')
            ?? reset($nodes);

        if (! is_array($startNode) || ! isset($startNode['id'])) {
            throw new RuntimeException('Unable to resolve start node.');
        }

        $currentNode = $indexedNodes[$callSession->current_node_id] ?? $startNode;
        $context = new CallContext($callSession, $callSession->variables ?? []);
        $path = [];

        while ($currentNode) {
            $nodeId = (string) ($currentNode['id'] ?? '');
            $nodeType = (string) ($currentNode['type'] ?? 'unknown');

            $callSession->forceFill([
                'current_node_id' => $nodeId,
                'state' => 'executing',
                'lock_version' => $callSession->lock_version + 1,
            ])->save();

            $this->traceWriter->write($callSession, 'flow.node.executing', [
                'node' => $currentNode,
            ], $nodeId, $nodeType);

            $handler = $this->nodeHandlerFactory->make($nodeType);
            $result = $handler->execute($currentNode, $context);

            $path[] = [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'transition' => $result->transition,
                'wait_for_event' => $result->waitForEvent,
                'completed' => $result->completed,
            ];

            $this->traceWriter->write($callSession, 'flow.node.executed', [
                'transition' => $result->transition,
                'wait_for_event' => $result->waitForEvent,
                'completed' => $result->completed,
                'payload' => $result->payload,
            ], $nodeId, $nodeType);

            if ($result->payload !== []) {
                $callSession->forceFill([
                    'variables' => array_merge($callSession->variables ?? [], $result->payload),
                    'lock_version' => $callSession->lock_version + 1,
                ])->save();

                $context = $context->withVariables($result->payload);
            }

            if ($result->completed) {
                $callSession->forceFill([
                    'state' => 'completed',
                    'lock_version' => $callSession->lock_version + 1,
                ])->save();

                return [
                    'status' => 'completed',
                    'path' => $path,
                ];
            }

            if ($result->waitForEvent) {
                $callSession->forceFill([
                    'state' => 'waiting',
                    'variables' => array_merge($callSession->variables ?? [], [
                        'wait_for_event' => $result->waitForEvent,
                        'waiting_node_id' => $nodeId,
                        'waiting_node_type' => $nodeType,
                    ]),
                    'lock_version' => $callSession->lock_version + 1,
                ])->save();

                return [
                    'status' => 'waiting',
                    'path' => $path,
                    'wait_for_event' => $result->waitForEvent,
                ];
            }

            $nextEdge = $this->edgeResolver->resolve($edges, $nodeId, $result->transition);

            if (! $nextEdge) {
                $callSession->forceFill([
                    'state' => 'stalled',
                    'lock_version' => $callSession->lock_version + 1,
                ])->save();

                return [
                    'status' => 'stalled',
                    'path' => $path,
                ];
            }

            $nextNodeId = $nextEdge['target'] ?? $nextEdge['target_node_id'] ?? null;
            $currentNode = $nextNodeId ? ($indexedNodes[$nextNodeId] ?? null) : null;
        }

        $callSession->forceFill([
            'state' => 'stalled',
            'lock_version' => $callSession->lock_version + 1,
        ])->save();

        return [
            'status' => 'stalled',
            'path' => $path,
        ];
    }
}
