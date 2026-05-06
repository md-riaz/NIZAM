<?php

namespace App\Observers;

use App\Models\Agent;
use App\Services\Flow\FlowArtifactService;
use App\Services\WallboardProjectionService;

class AgentObserver
{
    public function created(Agent $agent): void
    {
        $service = app(WallboardProjectionService::class);
        $service->refreshAgentProjection($agent);
        $service->refreshAgentQueues($agent);
    }

    public function updated(Agent $agent): void
    {
        $service = app(WallboardProjectionService::class);
        $service->refreshAgentProjection($agent);
        $service->refreshAgentQueues($agent);

        if (array_key_exists('is_active', $agent->getChanges())) {
            app(FlowArtifactService::class)->refreshTeamRoutingArtifactsForAgent($agent);
        }
    }

    public function deleted(Agent $agent): void
    {
        app(FlowArtifactService::class)->refreshTeamRoutingArtifactsForAgent($agent);

        $queueIds = $agent->queues()->pluck('queues.id');
        $service = app(WallboardProjectionService::class);
        $service->deleteAgentProjection($agent->id);

        foreach ($queueIds as $queueId) {
            $service->refreshQueueProjection($queueId);
        }
    }
}
