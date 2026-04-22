<?php

namespace App\Http\Controllers\Api;

use App\Data\QueueData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQueueRequest;
use App\Http\Requests\UpdateQueueRequest;
use App\Http\Resources\QueueResource;
use App\Models\Agent;
use App\Models\Queue;
use App\Models\Organization;
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
     * List queues for an organization.
     */
    public function index(Organization $organization)
    {
        return QueueResource::collection(
            $organization->queues()->withCount('members')->orderByDesc('id')->paginate(15)
        );
    }

    /**
     * Create a new queue.
     */
    public function store(StoreQueueRequest $request, Organization $organization): JsonResponse
    {
        $queue = $organization->queues()->create(QueueData::fromArray($request->validated())->attributes);

        return (new QueueResource($queue))->response()->setStatusCode(201);
    }

    /**
     * Show a single queue.
     */
    public function show(Organization $organization, Queue $queue): JsonResponse|QueueResource
    {
        if ($queue->organization_id !== $organization->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $queue->loadCount('members');

        return new QueueResource($queue);
    }

    /**
     * Update a queue.
     */
    public function update(UpdateQueueRequest $request, Organization $organization, Queue $queue): JsonResponse|QueueResource
    {
        if ($queue->organization_id !== $organization->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $queue->update(QueueData::fromArray($request->validated())->attributes);
        $queue->loadCount('members');

        return new QueueResource($queue);
    }

    /**
     * Delete a queue.
     */
    public function destroy(Organization $organization, Queue $queue): JsonResponse
    {
        if ($queue->organization_id !== $organization->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $queue->delete();

        return response()->json(null, 204);
    }

    /**
     * Add a member (agent) to a queue.
     */
    public function addMember(Request $request, Organization $organization, Queue $queue): JsonResponse
    {
        if ($queue->organization_id !== $organization->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        $payload = $request->validate([
            'agent_id' => 'required|uuid',
            'priority' => 'sometimes|integer|min:0',
        ]);

        $agent = $this->queueMembershipService->addMember(
            $organization,
            $queue,
            $payload['agent_id'],
            $payload['priority'] ?? 0,
        );

        if (! $agent) {
            $exists = $organization->agents()->whereKey($payload['agent_id'])->exists();

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
    public function removeMember(Organization $organization, Queue $queue, Agent $agent): JsonResponse
    {
        if ($queue->organization_id !== $organization->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        if ($agent->organization_id !== $organization->id) {
            return response()->json(['message' => 'Agent not found.'], 404);
        }

        $this->queueMembershipService->removeMember($queue, $agent);

        return response()->json(null, 204);
    }

    /**
     * List members of a queue.
     */
    public function members(Organization $organization, Queue $queue): JsonResponse
    {
        if ($queue->organization_id !== $organization->id) {
            return response()->json(['message' => 'Queue not found.'], 404);
        }

        return response()->json([
            'data' => $this->queueMembershipService->listMembers($queue),
        ]);
    }
}
