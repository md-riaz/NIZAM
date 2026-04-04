<?php

namespace App\Http\Controllers\Api;

use App\Data\QueueData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQueueRequest;
use App\Http\Requests\UpdateQueueRequest;
use App\Http\Resources\QueueResource;
use App\Models\Agent;
use App\Models\Queue;
use App\Models\Tenant;
use App\Services\QueueMembershipService;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(
        protected QueueService $queueService,
        protected QueueMembershipService $queueMembershipService,
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
        $queue = $tenant->queues()->create(QueueData::fromArray($request->validated())->attributes);

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

        $queue->update(QueueData::fromArray($request->validated())->attributes);
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

        $payload = $request->validate([
            'agent_id' => 'required|uuid',
            'priority' => 'sometimes|integer|min:0',
        ]);

        $agent = $this->queueMembershipService->addMember(
            $tenant,
            $queue,
            $payload['agent_id'],
            $payload['priority'] ?? 0,
        );

        if (! $agent) {
            $exists = $tenant->agents()->whereKey($payload['agent_id'])->exists();

            return response()->json([
                'message' => $exists
                    ? 'Agent is already a member of this queue.'
                    : 'Agent not found.',
            ], $exists ? 422 : 404);
        }

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

        $this->queueMembershipService->removeMember($queue, $agent);

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

        return response()->json([
            'data' => $this->queueMembershipService->listMembers($queue),
        ]);
    }
}
