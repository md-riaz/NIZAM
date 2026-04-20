<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\QueueMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WallboardReadService
{
    public function __construct(
        protected MetricsService $metricsService
    ) {}

    public function getWallboardData(string $organizationId): array
    {
        return Cache::remember(
            sprintf('metrics:%s:wallboard', $organizationId),
            now()->addSeconds(15),
            function () use ($organizationId): array {
                $queues = Queue::query()
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true)
                    ->get();

                $queueIds = $queues->pluck('id');
                $currentHourStart = now()->startOfHour();
                $livePeriodStart = now()->subHour();

                $aggregatedMetrics = QueueMetric::query()
                    ->where('organization_id', $organizationId)
                    ->where('period', QueueMetric::PERIOD_HOURLY)
                    ->where('period_start', $currentHourStart)
                    ->whereIn('queue_id', $queueIds)
                    ->get()
                    ->keyBy('queue_id');

                $missingQueueIds = $queueIds
                    ->reject(fn ($queueId) => $aggregatedMetrics->has($queueId))
                    ->values();

                [$queueEntryStats, $queueFallbackSlaCounts] = $this->buildFallbackQueueStats($missingQueueIds, $livePeriodStart);

                return [
                    'queues' => $this->mapQueueMetrics(
                        $queues,
                        $aggregatedMetrics,
                        $queueEntryStats,
                        $queueFallbackSlaCounts,
                        $this->getWaitingCounts($queueIds),
                        $this->getQueueMemberStats($queueIds),
                    ),
                    'agent_states' => $this->metricsService->getAgentStatesSummary($organizationId),
                    'agents' => $this->getAgents($organizationId),
                ];
            }
        );
    }

    private function buildFallbackQueueStats(Collection $missingQueueIds, $livePeriodStart): array
    {
        if ($missingQueueIds->isEmpty()) {
            return [collect(), collect()];
        }

        $queueEntryStats = QueueEntry::query()
            ->whereIn('queue_id', $missingQueueIds)
            ->where('join_time', '>=', $livePeriodStart)
            ->select('queue_id')
            ->selectRaw('COUNT(*) as offered')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as answered', [QueueEntry::STATUS_ANSWERED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as abandoned', [QueueEntry::STATUS_ABANDONED])
            ->selectRaw('AVG(CASE WHEN status = ? THEN wait_duration END) as avg_wait', [QueueEntry::STATUS_ANSWERED])
            ->selectRaw('MAX(CASE WHEN status = ? THEN wait_duration END) as max_wait', [QueueEntry::STATUS_ANSWERED])
            ->groupBy('queue_id')
            ->get()
            ->keyBy('queue_id');

        $queueFallbackSlaCounts = QueueEntry::query()
            ->join('queues', 'queues.id', '=', 'queue_entries.queue_id')
            ->whereIn('queue_entries.queue_id', $missingQueueIds)
            ->where('queue_entries.join_time', '>=', $livePeriodStart)
            ->where('queue_entries.status', QueueEntry::STATUS_ANSWERED)
            ->whereColumn('queue_entries.wait_duration', '<=', 'queues.service_level_threshold')
            ->select('queue_entries.queue_id')
            ->selectRaw('COUNT(*) as within_sla')
            ->groupBy('queue_entries.queue_id')
            ->pluck('within_sla', 'queue_entries.queue_id');

        return [$queueEntryStats, collect($queueFallbackSlaCounts)];
    }

    private function getWaitingCounts(Collection $queueIds): Collection
    {
        if ($queueIds->isEmpty()) {
            return collect();
        }

        return QueueEntry::query()
            ->whereIn('queue_id', $queueIds)
            ->where('status', QueueEntry::STATUS_WAITING)
            ->select('queue_id')
            ->selectRaw('COUNT(*) as waiting_count')
            ->groupBy('queue_id')
            ->pluck('waiting_count', 'queue_id');
    }

    private function getQueueMemberStats(Collection $queueIds): Collection
    {
        if ($queueIds->isEmpty()) {
            return collect();
        }

        return DB::table('queue_members')
            ->join('agents', 'agents.id', '=', 'queue_members.agent_id')
            ->whereIn('queue_members.queue_id', $queueIds)
            ->where('agents.is_active', true)
            ->select('queue_members.queue_id')
            ->selectRaw('COUNT(*) as total_members')
            ->selectRaw('SUM(CASE WHEN agents.state = ? THEN 1 ELSE 0 END) as busy_agents', [Agent::STATE_BUSY])
            ->groupBy('queue_members.queue_id')
            ->get()
            ->keyBy('queue_id');
    }

    private function mapQueueMetrics(
        Collection $queues,
        Collection $aggregatedMetrics,
        Collection $queueEntryStats,
        Collection $queueFallbackSlaCounts,
        Collection $queueWaitingCounts,
        Collection $queueMemberStats,
    ): Collection {
        return $queues->map(function (Queue $queue) use (
            $aggregatedMetrics,
            $queueEntryStats,
            $queueFallbackSlaCounts,
            $queueWaitingCounts,
            $queueMemberStats,
        ): array {
            $metric = $aggregatedMetrics->get($queue->id);
            $entryStats = $queueEntryStats->get($queue->id);
            $offered = (int) ($metric?->calls_offered ?? $entryStats?->offered ?? 0);
            $abandoned = (int) ($metric?->calls_abandoned ?? $entryStats?->abandoned ?? 0);
            $totalMembers = (int) ($queueMemberStats->get($queue->id)?->total_members ?? 0);
            $busyAgents = (int) ($queueMemberStats->get($queue->id)?->busy_agents ?? 0);
            $withinSla = (int) ($queueFallbackSlaCounts->get($queue->id, 0));

            return [
                'queue_id' => $queue->id,
                'queue_name' => $queue->name,
                'waiting_count' => (int) ($queueWaitingCounts->get($queue->id, 0)),
                'calls_offered' => $offered,
                'calls_answered' => (int) ($metric?->calls_answered ?? $entryStats?->answered ?? 0),
                'calls_abandoned' => $abandoned,
                'average_wait_time' => round((float) ($metric?->average_wait_time ?? $entryStats?->avg_wait ?? 0), 2),
                'max_wait_time' => (float) ($metric?->max_wait_time ?? $entryStats?->max_wait ?? 0),
                'service_level' => $metric ? (float) $metric->service_level : ($offered > 0 ? round(($withinSla / $offered) * 100, 2) : 100),
                'abandon_rate' => $metric ? (float) $metric->abandon_rate : ($offered > 0 ? round(($abandoned / $offered) * 100, 2) : 0),
                'agent_occupancy' => $totalMembers > 0 ? round(($busyAgents / $totalMembers) * 100, 2) : 0,
            ];
        })->values();
    }

    private function getAgents(string $organizationId): Collection
    {
        return Agent::query()
            ->where('organization_id', $organizationId)
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
    }
}
