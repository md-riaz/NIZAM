<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\QueueMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MetricsService
{
    /**
     * Get real-time metrics for a queue.
     */
    public function getRealTimeMetrics(Queue $queue): array
    {
        $waitingCount = $queue->waitingEntries()->count();

        $now = now();
        $periodStart = $now->copy()->subHour();

        $entries = QueueEntry::where('queue_id', $queue->id)
            ->where('join_time', '>=', $periodStart)
            ->get();

        $offered = $entries->count();
        $answered = $entries->where('status', QueueEntry::STATUS_ANSWERED)->count();
        $abandoned = $entries->where('status', QueueEntry::STATUS_ABANDONED)->count();

        $answeredEntries = $entries->where('status', QueueEntry::STATUS_ANSWERED);
        $avgWait = $answeredEntries->isNotEmpty()
            ? round($answeredEntries->avg('wait_duration'), 2)
            : 0;
        $maxWait = $answeredEntries->isNotEmpty()
            ? (float) $answeredEntries->max('wait_duration')
            : 0;

        $abandonRate = $offered > 0 ? round(($abandoned / $offered) * 100, 2) : 0;

        $withinSla = $answeredEntries
            ->where('wait_duration', '<=', $queue->service_level_threshold)
            ->count();
        $serviceLevel = $offered > 0 ? round(($withinSla / $offered) * 100, 2) : 100;

        $totalMembers = $queue->members()->where('is_active', true)->count();
        $busyAgents = $queue->members()
            ->where('is_active', true)
            ->where('state', Agent::STATE_BUSY)
            ->count();
        $occupancy = $totalMembers > 0 ? round(($busyAgents / $totalMembers) * 100, 2) : 0;

        return [
            'queue_id' => $queue->id,
            'queue_name' => $queue->name,
            'waiting_count' => $waitingCount,
            'calls_offered' => $offered,
            'calls_answered' => $answered,
            'calls_abandoned' => $abandoned,
            'average_wait_time' => $avgWait,
            'max_wait_time' => $maxWait,
            'service_level' => $serviceLevel,
            'abandon_rate' => $abandonRate,
            'agent_occupancy' => $occupancy,
        ];
    }

    /**
     * Aggregate metrics for a queue into a QueueMetric record.
     */
    public function aggregateMetrics(Queue $queue, string $period = QueueMetric::PERIOD_HOURLY, ?Carbon $periodStart = null): QueueMetric
    {
        $periodStart = $periodStart ?? now()->startOfHour();
        $periodEnd = $period === QueueMetric::PERIOD_HOURLY
            ? $periodStart->copy()->addHour()
            : $periodStart->copy()->addDay();

        $entries = QueueEntry::where('queue_id', $queue->id)
            ->where('join_time', '>=', $periodStart)
            ->where('join_time', '<', $periodEnd)
            ->get();

        $offered = $entries->count();
        $answered = $entries->where('status', QueueEntry::STATUS_ANSWERED)->count();
        $abandoned = $entries->where('status', QueueEntry::STATUS_ABANDONED)->count();

        $answeredEntries = $entries->where('status', QueueEntry::STATUS_ANSWERED);
        $avgWait = $answeredEntries->isNotEmpty()
            ? round($answeredEntries->avg('wait_duration'), 2)
            : 0;
        $maxWait = $answeredEntries->isNotEmpty()
            ? (float) $answeredEntries->max('wait_duration')
            : 0;

        $abandonRate = $offered > 0 ? round(($abandoned / $offered) * 100, 2) : 0;

        $withinSla = $answeredEntries
            ->where('wait_duration', '<=', $queue->service_level_threshold)
            ->count();
        $serviceLevel = $offered > 0 ? round(($withinSla / $offered) * 100, 2) : 100;

        $totalMembers = $queue->members()->where('is_active', true)->count();
        $busyAgents = $queue->members()
            ->where('is_active', true)
            ->where('state', Agent::STATE_BUSY)
            ->count();
        $occupancy = $totalMembers > 0 ? round(($busyAgents / $totalMembers) * 100, 2) : 0;

        return QueueMetric::updateOrCreate(
            [
                'queue_id' => $queue->id,
                'period' => $period,
                'period_start' => $periodStart,
            ],
            [
                'tenant_id' => $queue->tenant_id,
                'calls_offered' => $offered,
                'calls_answered' => $answered,
                'calls_abandoned' => $abandoned,
                'average_wait_time' => $avgWait,
                'max_wait_time' => $maxWait,
                'service_level' => $serviceLevel,
                'abandon_rate' => $abandonRate,
                'agent_occupancy' => $occupancy,
            ]
        );
    }

    /**
     * Get agent states summary for a tenant.
     */
    public function getAgentStatesSummary(string $tenantId): array
    {
        $states = Agent::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('state', DB::raw('count(*) as count'))
            ->groupBy('state')
            ->pluck('count', 'state')
            ->toArray();

        return [
            Agent::STATE_AVAILABLE => $states[Agent::STATE_AVAILABLE] ?? 0,
            Agent::STATE_BUSY => $states[Agent::STATE_BUSY] ?? 0,
            Agent::STATE_RINGING => $states[Agent::STATE_RINGING] ?? 0,
            Agent::STATE_PAUSED => $states[Agent::STATE_PAUSED] ?? 0,
            Agent::STATE_OFFLINE => $states[Agent::STATE_OFFLINE] ?? 0,
        ];
    }

    /**
     * Get wallboard data for a tenant.
     */
    public function getWallboardData(string $tenantId): array
    {
        $queues = Queue::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $queueMetrics = $queues->map(fn (Queue $queue) => $this->getRealTimeMetrics($queue))->values();

        $agentStates = $this->getAgentStatesSummary($tenantId);

        $agents = Agent::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('extension:id,extension,directory_first_name,directory_last_name')
            ->get()
            ->map(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'role' => $agent->role,
                'state' => $agent->state,
                'pause_reason' => $agent->pause_reason,
                'state_changed_at' => $agent->state_changed_at?->toIso8601String(),
                'extension' => $agent->extension?->extension,
            ])
            ->values();

        return [
            'queues' => $queueMetrics,
            'agent_states' => $agentStates,
            'agents' => $agents,
        ];
    }
}
