<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Queue;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class QueueMembershipService
{
    public function __construct(
        protected WallboardProjectionService $wallboardProjectionService,
    ) {}
    public function addMember(Tenant $tenant, Queue $queue, string $agentId, int $priority = 0): ?Agent
    {
        $agent = $tenant->agents()->find($agentId);

        if (! $agent || $queue->members()->where('agent_id', $agent->id)->exists()) {
            return null;
        }

        $queue->members()->attach($agent->id, [
            'id' => (string) Str::uuid(),
            'priority' => $priority,
        ]);

        $this->wallboardProjectionService->refreshAgentProjection($agent);
        $this->wallboardProjectionService->refreshQueueProjection($queue);

        return $agent;
    }

    public function removeMember(Queue $queue, Agent $agent): void
    {
        $queue->members()->detach($agent->id);

        $this->wallboardProjectionService->refreshQueueProjection($queue);
    }

    public function listMembers(Queue $queue): Collection
    {
        return $queue->members()
            ->with('extension:id,extension,directory_first_name,directory_last_name')
            ->get()
            ->map(fn (Agent $agent) => [
                'agent_id' => $agent->id,
                'name' => $agent->name,
                'role' => $agent->role,
                'state' => $agent->state,
                'priority' => $agent->pivot->priority,
                'extension' => $agent->extension?->extension,
            ]);
    }
}
