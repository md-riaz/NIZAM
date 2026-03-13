<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFlowRequest;
use App\Http\Requests\UpdateFlowRequest;
use App\Http\Resources\FlowResource;
use App\Models\Flow;
use App\Models\Tenant;
use App\Services\Flow\FlowGraphService;
use App\Services\Flow\FlowPublishService;
use Illuminate\Http\JsonResponse;

class FlowController extends Controller
{
    public function __construct(
        protected FlowGraphService $flowGraphService,
        protected FlowPublishService $flowPublishService,
    ) {}

    public function index(Tenant $tenant)
    {
        return FlowResource::collection($tenant->flows()->with(['activeVersion', 'versions'])->paginate(15));
    }

    public function store(StoreFlowRequest $request, Tenant $tenant): JsonResponse
    {
        $flow = $this->flowGraphService->createFlowWithVersion($tenant->id, $request->validated());

        return (new FlowResource($flow))->response()->setStatusCode(201);
    }

    public function show(Tenant $tenant, Flow $flow): JsonResponse|FlowResource
    {
        if ($flow->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Flow not found.'], 404);
        }

        return new FlowResource($flow->load(['activeVersion.nodes', 'activeVersion.edges', 'versions']));
    }

    public function update(UpdateFlowRequest $request, Tenant $tenant, Flow $flow): JsonResponse|FlowResource
    {
        if ($flow->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Flow not found.'], 404);
        }

        $flow = $this->flowGraphService->updateFlowWithVersion($flow, $request->validated());

        return new FlowResource($flow->load(['activeVersion.nodes', 'activeVersion.edges', 'versions']));
    }

    public function destroy(Tenant $tenant, Flow $flow): JsonResponse
    {
        if ($flow->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Flow not found.'], 404);
        }

        $flow->delete();

        return response()->json(null, 204);
    }

    public function publish(Tenant $tenant, Flow $flow): JsonResponse|FlowResource
    {
        if ($flow->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Flow not found.'], 404);
        }

        $version = $flow->versions()->latest('version_number')->first();

        if (! $version) {
            return response()->json(['message' => 'No flow version available to publish.'], 422);
        }

        $this->flowPublishService->publish($version);

        return new FlowResource($flow->fresh(['activeVersion.nodes', 'activeVersion.edges', 'versions']));
    }
}
