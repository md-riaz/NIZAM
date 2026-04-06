<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\QueueMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MetricsService
{
    /**
     * Get real-time metrics for a queue.
     */
    public function getRealTimeMetrics(Queue $queue): array
    {
        $periodStart = now()->subHour();

        $entryStats = QueueEntry::query()
            ->where('queue_id', $queue->id)
            ->where('join_time', '>=', $periodStart)
            ->selectRaw('COUNT(*) as offered')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as answered', [QueueEntry::STATUS_ANSWERED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as abandoned', [QueueEntry::STATUS_ABANDONED])
            ->selectRaw('AVG(CASE WHEN status = ? THEN wait_duration END) as avg_wait', [QueueEntry::STATUS_ANSWERED])
            ->selectRaw('MAX(CASE WHEN status = ? THEN wait_duration END) as max_wait', [QueueEntry::STATUS_ANSWERED])
            ->selectRaw('SUM(CASE WHEN status = ? AND wait_duration <= ? THEN 1 ELSE 0 END) as within_sla', [QueueEntry::STATUS_ANSWERED, $queue->service_level_threshold])
            ->first();

        $waitingCount = $queue->waitingEntries()->count();
        $offered = (int) ($entryStats?->offered ?? 0);
        $answered = (int) ($entryStats?->answered ?? 0);
        $abandoned = (int) ($entryStats?->abandoned ?? 0);
        $avgWait = round((float) ($entryStats?->avg_wait ?? 0), 2);
        $maxWait = (float) ($entryStats?->max_wait ?? 0);
        $withinSla = (int) ($entryStats?->within_sla ?? 0);
        $abandonRate = $offered > 0 ? round(($abandoned / $offered) * 100, 2) : 0;
        $serviceLevel = $offered > 0 ? round(($withinSla / $offered) * 100, 2) : 100;

        $memberStats = $queue->members()
            ->where('is_active', true)
            ->selectRaw('COUNT(*) as total_members')
            ->selectRaw('SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as busy_agents', [Agent::STATE_BUSY])
            ->first();

        $totalMembers = (int) ($memberStats?->total_members ?? 0);
        $busyAgents = (int) ($memberStats?->busy_agents ?? 0);
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

        $entryStats = QueueEntry::query()
            ->where('queue_id', $queue->id)
            ->where('join_time', '>=', $periodStart)
            ->where('join_time', '<', $periodEnd)
            ->selectRaw('COUNT(*) as offered')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as answered', [QueueEntry::STATUS_ANSWERED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as abandoned', [QueueEntry::STATUS_ABANDONED])
            ->selectRaw('AVG(CASE WHEN status = ? THEN wait_duration END) as avg_wait', [QueueEntry::STATUS_ANSWERED])
            ->selectRaw('MAX(CASE WHEN status = ? THEN wait_duration END) as max_wait', [QueueEntry::STATUS_ANSWERED])
            ->selectRaw('SUM(CASE WHEN status = ? AND wait_duration <= ? THEN 1 ELSE 0 END) as within_sla', [QueueEntry::STATUS_ANSWERED, $queue->service_level_threshold])
            ->first();

        $offered = (int) ($entryStats?->offered ?? 0);
        $answered = (int) ($entryStats?->answered ?? 0);
        $abandoned = (int) ($entryStats?->abandoned ?? 0);
        $avgWait = round((float) ($entryStats?->avg_wait ?? 0), 2);
        $maxWait = (float) ($entryStats?->max_wait ?? 0);
        $withinSla = (int) ($entryStats?->within_sla ?? 0);
        $abandonRate = $offered > 0 ? round(($abandoned / $offered) * 100, 2) : 0;
        $serviceLevel = $offered > 0 ? round(($withinSla / $offered) * 100, 2) : 100;

        $memberStats = $queue->members()
            ->where('is_active', true)
            ->selectRaw('COUNT(*) as total_members')
            ->selectRaw('SUM(CASE WHEN state = ? THEN 1 ELSE 0 END) as busy_agents', [Agent::STATE_BUSY])
            ->first();

        $totalMembers = (int) ($memberStats?->total_members ?? 0);
        $busyAgents = (int) ($memberStats?->busy_agents ?? 0);
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
        return Cache::remember(
            sprintf('metrics:%s:agent-states', $tenantId),
            now()->addSeconds(15),
            function () use ($tenantId): array {
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
        );
    }

    /**
     * Get wallboard data for a tenant.
     */
    public function getWallboardData(string $tenantId): array
    {
        return app(WallboardProjectionService::class)->getWallboardData($tenantId);
    }
}
