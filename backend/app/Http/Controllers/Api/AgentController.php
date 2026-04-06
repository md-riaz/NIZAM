<?php

namespace App\Http\Controllers\Api;

use App\Events\ContactCenterEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentStateChangeRequest;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class AgentController extends Controller
{
    /**
     * List agents for a tenant.
     */
    public function index(Tenant $tenant)
    {
        return AgentResource::collection(
            $tenant->agents()->with('extension')->paginate(15)
        );
    }

    /**
     * Create a new agent.
     */
    public function store(StoreAgentRequest $request, Tenant $tenant): JsonResponse
    {
        $agent = $tenant->agents()->create($request->validated());
        $agent->load('extension');

        return (new AgentResource($agent))->response()->setStatusCode(201);
    }

    /**
     * Show a single agent.
     */
    public function show(Tenant $tenant, Agent $agent): JsonResponse|AgentResource
    {
        if ($agent->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $agent->load('extension');

        return new AgentResource($agent);
    }

    /**
     * Update an agent.
     */
    public function update(UpdateAgentRequest $request, Tenant $tenant, Agent $agent): JsonResponse|AgentResource
    {
        if ($agent->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $agent->update($request->validated());
        $agent->load('extension');

        return new AgentResource($agent);
    }

    /**
     * Delete an agent.
     */
    public function destroy(Tenant $tenant, Agent $agent): JsonResponse
    {
        if ($agent->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $agent->delete();

        return response()->json(null, 204);
    }

    /**
     * Change agent state via API.
     */
    public function changeState(AgentStateChangeRequest $request, Tenant $tenant, Agent $agent): JsonResponse|AgentResource
    {
        if ($agent->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $agent->transitionState(
            $request->validated('state'),
            $request->validated('pause_reason')
        );

        $agent->load('extension');

        ContactCenterEvent::dispatch($tenant->id, 'agent.state_changed', [
            'agent_id' => $agent->id,
            'state' => $agent->state,
            'pause_reason' => $agent->pause_reason,
            'previous_state' => $agent->getOriginal('state'),
        ]);

        return new AgentResource($agent);
    }
}
