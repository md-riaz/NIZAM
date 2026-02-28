<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCallFlowRequest;
use App\Http\Requests\UpdateCallFlowRequest;
use App\Http\Resources\CallFlowResource;
use App\Models\CallFlow;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing call flows scoped to a tenant.
 */
class CallFlowController extends Controller
{
    /**
     * List call flows for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        $this->authorize('viewAny', CallFlow::class);

        return CallFlowResource::collection($tenant->callFlows()->paginate(15));
    }

    /**
     * Create a new call flow for a tenant.
     */
    public function store(StoreCallFlowRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', CallFlow::class);

        $callFlow = $tenant->callFlows()->create($request->validated());

        return (new CallFlowResource($callFlow))->response()->setStatusCode(201);
    }

    /**
     * Show a single call flow.
     */
    public function show(Tenant $tenant, CallFlow $callFlow): JsonResponse|CallFlowResource
    {
        if ($callFlow->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Call flow not found.'], 404);
        }

        $this->authorize('view', $callFlow);

        return new CallFlowResource($callFlow);
    }

    /**
     * Update an existing call flow.
     */
    public function update(UpdateCallFlowRequest $request, Tenant $tenant, CallFlow $callFlow): JsonResponse|CallFlowResource
    {
        if ($callFlow->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Call flow not found.'], 404);
        }

        $this->authorize('update', $callFlow);

        $callFlow->update($request->validated());

        return new CallFlowResource($callFlow);
    }

    /**
     * Delete a call flow.
     */
    public function destroy(Tenant $tenant, CallFlow $callFlow): JsonResponse
    {
        if ($callFlow->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Call flow not found.'], 404);
        }

        $this->authorize('delete', $callFlow);

        $callFlow->delete();

        return response()->json(null, 204);
    }
}
