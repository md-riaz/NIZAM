<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCallRoutingPolicyRequest;
use App\Http\Requests\UpdateCallRoutingPolicyRequest;
use App\Http\Resources\CallRoutingPolicyResource;
use App\Models\CallRoutingPolicy;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing call routing policies scoped to a tenant.
 */
class CallRoutingPolicyController extends Controller
{
    /**
     * List call routing policies for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        $this->authorize('viewAny', CallRoutingPolicy::class);

        return CallRoutingPolicyResource::collection(
            $tenant->callRoutingPolicies()->orderBy('priority')->paginate(15)
        );
    }

    /**
     * Create a new call routing policy for a tenant.
     */
    public function store(StoreCallRoutingPolicyRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', CallRoutingPolicy::class);

        $policy = $tenant->callRoutingPolicies()->create($request->validated());

        return (new CallRoutingPolicyResource($policy))->response()->setStatusCode(201);
    }

    /**
     * Show a single call routing policy.
     */
    public function show(Tenant $tenant, CallRoutingPolicy $callRoutingPolicy): JsonResponse|CallRoutingPolicyResource
    {
        if ($callRoutingPolicy->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Call routing policy not found.'], 404);
        }

        $this->authorize('view', $callRoutingPolicy);

        return new CallRoutingPolicyResource($callRoutingPolicy);
    }

    /**
     * Update an existing call routing policy.
     */
    public function update(UpdateCallRoutingPolicyRequest $request, Tenant $tenant, CallRoutingPolicy $callRoutingPolicy): JsonResponse|CallRoutingPolicyResource
    {
        if ($callRoutingPolicy->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Call routing policy not found.'], 404);
        }

        $this->authorize('update', $callRoutingPolicy);

        $callRoutingPolicy->update($request->validated());

        return new CallRoutingPolicyResource($callRoutingPolicy);
    }

    /**
     * Delete a call routing policy.
     */
    public function destroy(Tenant $tenant, CallRoutingPolicy $callRoutingPolicy): JsonResponse
    {
        if ($callRoutingPolicy->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Call routing policy not found.'], 404);
        }

        $this->authorize('delete', $callRoutingPolicy);

        $callRoutingPolicy->delete();

        return response()->json(null, 204);
    }
}
