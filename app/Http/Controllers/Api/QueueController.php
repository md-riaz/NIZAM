<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQueueRequest;
use App\Http\Requests\UpdateQueueRequest;
use App\Http\Resources\QueueResource;
use App\Models\Agent;
use App\Models\Queue;
use App\Models\Tenant;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(
        protected QueueService $queueService
    ) {}

    /**
     * List queues for a tenant.
     */
    public function index(Tenant $tenant)
    {
        return QueueResource::collection(
            $tenant->queues()->withCount('members')->paginate(15)
        );
    }

    /**
     * Create a new queue.
     */
    public function store(StoreQueueRequest $request, Tenant $tenant): JsonResponse
    {
        $queue = $tenant->queues()->create($request->validated());

        return (new QueueResource($queue))->response()->setStatusCode(201);
    }

    /**
     * Show a single queue.
     */
    public function show(Tenant $tenant, Queue $queue): JsonResponse|QueueResource
    {
        if ($queue->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $queue->loadCount('members');

        return new QueueResource($queue);
    }

    /**
     * Update a queue.
     */
    public function update(UpdateQueueRequest $request, Tenant $tenant, Queue $queue): JsonResponse|QueueResource
    {
        if ($queue->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $queue->update($request->validated());
        $queue->loadCount('members');

        return new QueueResource($queue);
    }

    /**
     * Delete a queue.
     */
    public function destroy(Tenant $tenant, Queue $queue): JsonResponse
    {
        if ($queue->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $queue->delete();

        return response()->json(null, 204);
    }

    /**
     * Add a member (agent) to a queue.
     */
    public function addMember(Request $request, Tenant $tenant, Queue $queue): JsonResponse
    {
        if ($queue->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $request->validate([
            'agent_id' => 'required|uuid',
            'priority' => 'sometimes|integer|min:0',
        ]);

        $agent = $tenant->agents()->find($request->agent_id);
        if (! $agent) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        if ($queue->members()->where('agent_id', $agent->id)->exists()) {
            return response()->json(['message' => 'Agent is already a member of this queue.'], 422);
        }

        $queue->members()->attach($agent->id, [
            'id' => \Illuminate\Support\Str::uuid(),
            'priority' => $request->input('priority', 0),
        ]);

        return response()->json(['message' => 'Agent added to queue.'], 201);
    }

    /**
     * Remove a member (agent) from a queue.
     */
    public function removeMember(Tenant $tenant, Queue $queue, Agent $agent): JsonResponse
    {
        if ($queue->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        if ($agent->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $queue->members()->detach($agent->id);

        return response()->json(null, 204);
    }

    /**
     * List members of a queue.
     */
    public function members(Tenant $tenant, Queue $queue): JsonResponse
    {
        if ($queue->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $members = $queue->members()
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

        return response()->json(['data' => $members]);
    }
}
