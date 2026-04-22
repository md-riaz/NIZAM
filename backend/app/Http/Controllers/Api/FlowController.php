<?php

namespace App\Http\Controllers\Api;

use App\Data\FlowData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFlowRequest;
use App\Http\Requests\UpdateFlowRequest;
use App\Http\Resources\FlowResource;
use App\Models\Flow;
use App\Models\Organization;
use App\Services\Flow\FlowApplicationService;
use Illuminate\Http\JsonResponse;

class FlowController extends Controller
{
    public function __construct(
        protected FlowApplicationService $flowApplicationService,
    ) {}

    public function index(Organization $organization)
    {
        return FlowResource::collection($organization->flows()->with(['activeVersion', 'latestVersion', 'versions'])->orderByDesc('id')->paginate(15));
    }

    public function store(StoreFlowRequest $request, Organization $organization): JsonResponse
    {
        $flow = $this->flowApplicationService->create($organization->id, FlowData::fromArray($request->validated()));

        return (new FlowResource($flow))->response()->setStatusCode(201);
    }

    public function show(Organization $organization, Flow $flow): JsonResponse|FlowResource
    {
        if ($flow->organization_id !== $organization->id) {
            return response()->json(['message' => 'Flow not found.'], 404);
        }

        return new FlowResource($flow->load(['activeVersion.nodes', 'activeVersion.edges', 'latestVersion.nodes', 'latestVersion.edges', 'versions']));
    }

    public function update(UpdateFlowRequest $request, Organization $organization, Flow $flow): JsonResponse|FlowResource
    {
        if ($flow->organization_id !== $organization->id) {
            return response()->json(['message' => 'Flow not found.'], 404);
        }

        $payload = array_merge([
            'name' => $flow->name,
            'description' => $flow->description,
            'version' => ['definition' => []],
            'publish' => false,
        ], $request->validated());

        if (! isset($payload['version']['definition'])) {
            $payload['version']['definition'] = [];
        }

        $flow = $this->flowApplicationService->update($flow, FlowData::fromArray($payload));

        return new FlowResource($flow->load(['activeVersion.nodes', 'activeVersion.edges', 'latestVersion.nodes', 'latestVersion.edges', 'versions']));
    }

    public function destroy(Organization $organization, Flow $flow): JsonResponse
    {
        if ($flow->organization_id !== $organization->id) {
            return response()->json(['message' => 'Flow not found.'], 404);
        }

        $flow->delete();

        return response()->json(null, 204);
    }

    public function publish(Organization $organization, Flow $flow): JsonResponse|FlowResource
    {
        if ($flow->organization_id !== $organization->id) {
            return response()->json(['message' => 'Flow not found.'], 404);
        }

        $result = $this->flowApplicationService->publishLatest($flow);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return new FlowResource($flow->fresh(['activeVersion.nodes', 'activeVersion.edges', 'versions']));
    }
}
