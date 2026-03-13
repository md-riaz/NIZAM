<?php

namespace App\Services\Flow;

use App\Models\CallFlow;
use App\Models\CallSession;
use App\Services\Call\CallLockService;
use App\Services\Call\TraceWriter;

class FlowRuntimeStarter
{
    public function __construct(
        protected CallLockService $callLockService,
        protected FlowExecutionService $flowExecutionService,
        protected TraceWriter $traceWriter,
    ) {}

    public function start(CallSession $callSession, CallFlow $callFlow): array
    {
        return $this->callLockService->withLock($callSession->call_uuid, function () use ($callSession, $callFlow) {
            $definition = [
                'nodes' => is_array($callFlow->nodes) ? $callFlow->nodes : [],
                'edges' => $this->extractEdges($callFlow->nodes ?? []),
            ];

            $this->traceWriter->write($callSession, 'flow.runtime.starting', [
                'call_flow_id' => $callFlow->id,
                'flow_name' => $callFlow->name,
            ]);

            $result = $this->flowExecutionService->executeArrayDefinition($callSession, $definition);

            $this->traceWriter->write($callSession, 'flow.runtime.result', $result);

            return $result;
        });
    }

    protected function extractEdges(array $nodes): array
    {
        $edges = [];

        foreach ($nodes as $node) {
            foreach (($node['edges'] ?? []) as $edge) {
                $edge['source'] = $edge['source'] ?? ($node['id'] ?? null);
                $edges[] = $edge;
            }
        }

        return $edges;
    }
}
