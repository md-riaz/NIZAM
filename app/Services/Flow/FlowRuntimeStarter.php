<?php

namespace App\Services\Flow;

use App\Models\CallSession;
use App\Models\FlowVersion;
use App\Services\Call\CallLockService;
use App\Services\Call\TraceWriter;

class FlowRuntimeStarter
{
    public function __construct(
        protected CallLockService $callLockService,
        protected FlowExecutionService $flowExecutionService,
        protected FlowDefinitionMapper $flowDefinitionMapper,
        protected TraceWriter $traceWriter,
    ) {}

    public function start(CallSession $callSession, FlowVersion $flowVersion): array
    {
        return $this->callLockService->withLock($callSession->call_uuid, function () use ($callSession, $flowVersion) {
            $definition = $this->flowDefinitionMapper->toExecutionDefinition($flowVersion->loadMissing(['nodes', 'edges', 'flow']));

            $this->traceWriter->write($callSession, 'flow.runtime.starting', [
                'flow_id' => $flowVersion->flow_id,
                'flow_version_id' => $flowVersion->id,
                'flow_name' => $flowVersion->flow?->name,
            ]);

            $result = $this->flowExecutionService->executeArrayDefinition($callSession, $definition);

            $this->traceWriter->write($callSession, 'flow.runtime.result', $result);

            return $result;
        });
    }
}
