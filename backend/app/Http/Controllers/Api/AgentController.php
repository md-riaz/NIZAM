<?php

namespace App\Http\Controllers\Api;

use App\Events\ContactCenterEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentStateChangeRequest;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class AgentController extends Controller
{
    /**
     * List agents for an organization.
     */
    public function index(Organization $organization)
    {
        return AgentResource::collection(
            $organization->agents()->with('extension')->orderByDesc('id')->paginate(15)
        );
    }

    /**
     * Create a new agent.
     */
    public function store(StoreAgentRequest $request, Organization $organization): JsonResponse
    {
        $agent = $organization->agents()->create($request->validated());
        $agent->load('extension');

        return (new AgentResource($agent))->response()->setStatusCode(201);
    }

    /**
     * Show a single agent.
     */
    public function show(Organization $organization, Agent $agent): JsonResponse|AgentResource
    {
        if ($agent->organization_id !== $organization->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $agent->load('extension');

        return new AgentResource($agent);
    }

    /**
     * Update an agent.
     */
    public function update(UpdateAgentRequest $request, Organization $organization, Agent $agent): JsonResponse|AgentResource
    {
        if ($agent->organization_id !== $organization->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $agent->update($request->validated());
        $agent->load('extension');

        return new AgentResource($agent);
    }

    /**
     * Delete an agent.
     */
    public function destroy(Organization $organization, Agent $agent): JsonResponse
    {
        if ($agent->organization_id !== $organization->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $agent->delete();

        return response()->json(null, 204);
    }

    /**
     * Change agent state via API.
     */
    public function changeState(AgentStateChangeRequest $request, Organization $organization, Agent $agent): JsonResponse|AgentResource
    {
        if ($agent->organization_id !== $organization->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $agent->transitionState(
            $request->validated('state'),
            $request->validated('pause_reason')
        );

        $agent->load('extension');

        ContactCenterEvent::dispatch($organization->id, 'agent.state_changed', [
            'agent_id' => $agent->id,
            'state' => $agent->state,
            'pause_reason' => $agent->pause_reason,
            'previous_state' => $agent->getOriginal('state'),
        ]);

        return new AgentResource($agent);
    }
}
