<?php

namespace App\Services\Flow\Nodes;

use App\Domain\Flow\CallContext;
use App\Domain\Flow\Contracts\NodeHandler;
use App\Domain\Flow\NodeResult;
use App\Models\Schedule;
use App\Services\Schedule\ScheduleEngine;

class ScheduleCheckNodeHandler implements NodeHandler
{
    public function __construct(
        protected ScheduleEngine $scheduleEngine,
    ) {}

    public function execute(array $node, CallContext $context): NodeResult
    {
        $scheduleId = data_get($node, 'config.schedule_id');

        if (! $scheduleId) {
            $scheduleState = (string) ($context->variable('schedule_state', 'closed'));

            return NodeResult::transition($scheduleState, [
                'schedule_state' => $scheduleState,
            ]);
        }

        $schedule = Schedule::query()
            ->where('tenant_id', $context->tenantId())
            ->whereKey($scheduleId)
            ->where('is_active', true)
            ->first();

        $scheduleState = $schedule
            ? $this->scheduleEngine->evaluate($schedule)
            : 'closed';

        return NodeResult::transition($scheduleState, [
            'schedule_id' => $scheduleId,
            'schedule_state' => $scheduleState,
        ]);
    }
}
