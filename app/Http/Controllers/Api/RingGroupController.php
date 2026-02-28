<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRingGroupRequest;
use App\Http\Requests\UpdateRingGroupRequest;
use App\Http\Resources\RingGroupResource;
use App\Models\RingGroup;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing ring groups scoped to a tenant.
 */
class RingGroupController extends Controller
{
    /**
     * List ring groups for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        $this->authorize('viewAny', RingGroup::class);

        return RingGroupResource::collection($tenant->ringGroups()->paginate(15));
    }

    /**
     * Create a new ring group for a tenant.
     */
    public function store(StoreRingGroupRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', RingGroup::class);

        $ringGroup = $tenant->ringGroups()->create($request->validated());

        return (new RingGroupResource($ringGroup))->response()->setStatusCode(201);
    }

    /**
     * Show a single ring group.
     */
    public function show(Tenant $tenant, RingGroup $ringGroup): JsonResponse|RingGroupResource
    {
        if ($ringGroup->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Ring group not found.'], 404);
        }

        $this->authorize('view', $ringGroup);

        return new RingGroupResource($ringGroup);
    }

    /**
     * Update an existing ring group.
     */
    public function update(UpdateRingGroupRequest $request, Tenant $tenant, RingGroup $ringGroup): JsonResponse|RingGroupResource
    {
        if ($ringGroup->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Ring group not found.'], 404);
        }

        $this->authorize('update', $ringGroup);

        $ringGroup->update($request->validated());

        return new RingGroupResource($ringGroup);
    }

    /**
     * Delete a ring group.
     */
    public function destroy(Tenant $tenant, RingGroup $ringGroup): JsonResponse
    {
        if ($ringGroup->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Ring group not found.'], 404);
        }

        $this->authorize('delete', $ringGroup);

        $ringGroup->delete();

        return response()->json(null, 204);
    }
}
