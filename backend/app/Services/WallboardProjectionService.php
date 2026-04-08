<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Extension;
use App\Models\Queue;
use App\Models\QueueEntry;
use App\Models\QueueMetric;
use App\Models\WallboardAgentProjection;
use App\Models\WallboardQueueProjection;
use Illuminate\Support\Facades\DB;

class WallboardProjectionService
{
    public function getWallboardData(string $tenantId): array
    {
        $this->ensureTenantCoverage($tenantId);

        $agents = WallboardAgentProjection::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $states = $agents
            ->groupBy('state')
            ->map(fn ($group) => $group->count());

        return [
            'queues' => WallboardQueueProjection::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('queue_name')
                ->get([
                    'queue_id',
                    'queue_name',
                    'waiting_count',
                    'calls_offered',
                    'calls_answered',
                    'calls_abandoned',
                    'average_wait_time',
                    'max_wait_time',
                    'service_level',
                    'abandon_rate',
                    'agent_occupancy',
                ])
                ->map(fn (WallboardQueueProjection $projection) => [
                    'queue_id' => $projection->queue_id,
                    'queue_name' => $projection->queue_name,
                    'waiting_count' => $projection->waiting_count,
                    'calls_offered' => $projection->calls_offered,
                    'calls_answered' => $projection->calls_answered,
                    'calls_abandoned' => $projection->calls_abandoned,
                    'average_wait_time' => round((float) $projection->average_wait_time, 2),
                    'max_wait_time' => (float) $projection->max_wait_time,
                    'service_level' => (float) $projection->service_level,
                    'abandon_rate' => (float) $projection->abandon_rate,
                    'agent_occupancy' => (float) $projection->agent_occupancy,
                ])
                ->values(),
            'agent_states' => [
                Agent::STATE_AVAILABLE => (int) ($states[Agent::STATE_AVAILABLE] ?? 0),
                Agent::STATE_BUSY => (int) ($states[Agent::STATE_BUSY] ?? 0),
                Agent::STATE_RINGING => (int) ($states[Agent::STATE_RINGING] ?? 0),
                Agent::STATE_PAUSED => (int) ($states[Agent::STATE_PAUSED] ?? 0),
                Agent::STATE_OFFLINE => (int) ($states[Agent::STATE_OFFLINE] ?? 0),
            ],
            'agents' => $agents
                ->map(fn (WallboardAgentProjection $projection) => [
                    'id' => $projection->agent_id,
                    'name' => $projection->name,
                    'role' => $projection->role,
                    'state' => $projection->state,
                    'pause_reason' => $projection->pause_reason,
                    'state_changed_at' => $projection->state_changed_at?->toIso8601String(),
                    'extension' => $projection->extension,
                ])
                ->values(),
        ];
    }

    public function refreshQueueProjection(Queue|string $queue): void
    {
        $queue = $queue instanceof Queue ? $queue : Queue::query()->find($queue);

        if (! $queue || ! $queue->is_active) {
            if (is_string($queue)) {
                WallboardQueueProjection::query()->where('queue_id', $queue)->delete();
            }

            return;
        }

        $currentHourStart = now()->startOfHour();
        $livePeriodStart = now()->subHour();

        $metric = QueueMetric::query()
            ->where('tenant_id', $queue->tenant_id)
            ->where('queue_id', $queue->id)
            ->where('period', QueueMetric::PERIOD_HOURLY)
            ->where('period_start', $currentHourStart)
            ->first();

        $entryStats = null;
        $withinSla = 0;

        if (! $metric) {
            $entryStats = QueueEntry::query()
                ->where('queue_id', $queue->id)
                ->where('join_time', '>=', $livePeriodStart)
                ->selectRaw('COUNT(*) as offered')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as answered', [QueueEntry::STATUS_ANSWERED])
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as abandoned', [QueueEntry::STATUS_ABANDONED])
                ->selectRaw('AVG(CASE WHEN status = ? THEN wait_duration END) as avg_wait', [QueueEntry::STATUS_ANSWERED])
                ->selectRaw('MAX(CASE WHEN status = ? THEN wait_duration END) as max_wait', [QueueEntry::STATUS_ANSWERED])
                ->first();

            $withinSla = (int) QueueEntry::query()
                ->where('queue_id', $queue->id)
                ->where('join_time', '>=', $livePeriodStart)
                ->where('status', QueueEntry::STATUS_ANSWERED)
                ->where('wait_duration', '<=', $queue->service_level_threshold)
                ->count();
        }

        $offered = (int) ($metric?->calls_offered ?? $entryStats?->offered ?? 0);
        $abandoned = (int) ($metric?->calls_abandoned ?? $entryStats?->abandoned ?? 0);

        $memberStats = Agent::query()
            ->join('queue_members', 'agents.id', '=', 'queue_members.agent_id')
            ->where('queue_members.queue_id', $queue->id)
            ->where('agents.is_active', true)
            ->selectRaw('COUNT(*) as total_members')
            ->selectRaw('SUM(CASE WHEN agents.state = ? THEN 1 ELSE 0 END) as busy_agents', [Agent::STATE_BUSY])
            ->first();

        $totalMembers = (int) ($memberStats?->total_members ?? 0);
        $busyAgents = (int) ($memberStats?->busy_agents ?? 0);

        WallboardQueueProjection::query()->updateOrCreate(
            [
                'tenant_id' => $queue->tenant_id,
                'queue_id' => $queue->id,
            ],
            [
                'queue_name' => $queue->name,
                'waiting_count' => QueueEntry::query()->where('queue_id', $queue->id)->where('status', QueueEntry::STATUS_WAITING)->count(),
                'calls_offered' => $offered,
                'calls_answered' => (int) ($metric?->calls_answered ?? $entryStats?->answered ?? 0),
                'calls_abandoned' => $abandoned,
                'average_wait_time' => round((float) ($metric?->average_wait_time ?? $entryStats?->avg_wait ?? 0), 2),
                'max_wait_time' => (float) ($metric?->max_wait_time ?? $entryStats?->max_wait ?? 0),
                'service_level' => $metric ? (float) $metric->service_level : ($offered > 0 ? round(($withinSla / $offered) * 100, 2) : 100),
                'abandon_rate' => $metric ? (float) $metric->abandon_rate : ($offered > 0 ? round(($abandoned / $offered) * 100, 2) : 0),
                'agent_occupancy' => $totalMembers > 0 ? round(($busyAgents / $totalMembers) * 100, 2) : 0,
            ]
        );
    }

    public function deleteQueueProjection(string $queueId): void
    {
        WallboardQueueProjection::query()->where('queue_id', $queueId)->delete();
    }

    public function refreshAgentProjection(Agent|string $agent): void
    {
        $agent = $agent instanceof Agent ? $agent->loadMissing('extension') : Agent::query()->with('extension')->find($agent);

        if (! $agent) {
            if (is_string($agent)) {
                WallboardAgentProjection::query()->where('agent_id', $agent)->delete();
            }

            return;
        }

        WallboardAgentProjection::query()->updateOrCreate(
            [
                'tenant_id' => $agent->tenant_id,
                'agent_id' => $agent->id,
            ],
            [
                'name' => $agent->name,
                'role' => $agent->role,
                'state' => $agent->state ?? Agent::STATE_OFFLINE,
                'pause_reason' => $agent->pause_reason,
                'state_changed_at' => $agent->state_changed_at ?? now(),
                'extension' => $agent->extension?->extension,
                'is_active' => (bool) $agent->is_active,
            ]
        );
    }

    public function deleteAgentProjection(string $agentId): void
    {
        WallboardAgentProjection::query()->where('agent_id', $agentId)->delete();
    }

    public function refreshAgentQueues(Agent $agent): void
    {
        foreach ($agent->queues()->pluck('queues.id') as $queueId) {
            $this->refreshQueueProjection($queueId);
        }
    }

    public function refreshExtensionProjection(Extension $extension): void
    {
        $agent = $extension->agent;

        if ($agent) {
            $this->refreshAgentProjection($agent);
        }
    }

    private function ensureTenantCoverage(string $tenantId): void
    {
        $activeQueueIds = Queue::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->pluck('id');

        WallboardQueueProjection::query()
            ->where('tenant_id', $tenantId)
            ->when($activeQueueIds->isNotEmpty(), fn ($query) => $query->whereNotIn('queue_id', $activeQueueIds), fn ($query) => $query)
            ->delete();

        foreach ($activeQueueIds->diff(WallboardQueueProjection::query()->where('tenant_id', $tenantId)->pluck('queue_id')) as $queueId) {
            $this->refreshQueueProjection($queueId);
        }

        $agentIds = Agent::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->pluck('id');

        WallboardAgentProjection::query()
            ->where('tenant_id', $tenantId)
            ->when($agentIds->isNotEmpty(), fn ($query) => $query->whereNotIn('agent_id', $agentIds), fn ($query) => $query)
            ->delete();

        foreach ($agentIds->diff(WallboardAgentProjection::query()->where('tenant_id', $tenantId)->pluck('agent_id')) as $agentId) {
            $this->refreshAgentProjection($agentId);
        }
    }
}
