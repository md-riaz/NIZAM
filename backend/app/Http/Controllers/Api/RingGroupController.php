<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRingGroupRequest;
use App\Http\Requests\UpdateRingGroupRequest;
use App\Http\Resources\RingGroupResource;
use App\Models\RingGroup;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing ring groups scoped to a organization.
 */
class RingGroupController extends Controller
{
    /**
     * List ring groups for an organization (paginated).
     */
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', RingGroup::class);

        return RingGroupResource::collection($organization->ringGroups()->orderByDesc('id')->paginate(15));
    }

    /**
     * Create a new ring group for an organization.
     */
    public function store(StoreRingGroupRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', RingGroup::class);

        if ($organization->max_ring_groups > 0 && $organization->ringGroups()->count() >= $organization->max_ring_groups) {
            return response()->json([
                'message' => 'Ring group quota exceeded. Maximum allowed: '.$organization->max_ring_groups,
            ], 422);
        }

        $ringGroup = $organization->ringGroups()->create($request->validated());

        return (new RingGroupResource($ringGroup))->response()->setStatusCode(201);
    }

    /**
     * Show a single ring group.
     */
    public function show(Organization $organization, RingGroup $ringGroup): JsonResponse|RingGroupResource
    {
        if ($ringGroup->organization_id !== $organization->id) {
            return response()->json(['message' => 'Ring group not found.'], 404);
        }

        $this->authorize('view', $ringGroup);

        return new RingGroupResource($ringGroup);
    }

    /**
     * Update an existing ring group.
     */
    public function update(UpdateRingGroupRequest $request, Organization $organization, RingGroup $ringGroup): JsonResponse|RingGroupResource
    {
        if ($ringGroup->organization_id !== $organization->id) {
            return response()->json(['message' => 'Ring group not found.'], 404);
        }

        $this->authorize('update', $ringGroup);

        $ringGroup->update($request->validated());

        return new RingGroupResource($ringGroup);
    }

    /**
     * Delete a ring group.
     */
    public function destroy(Organization $organization, RingGroup $ringGroup): JsonResponse
    {
        if ($ringGroup->organization_id !== $organization->id) {
            return response()->json(['message' => 'Ring group not found.'], 404);
        }

        $this->authorize('delete', $ringGroup);

        $ringGroup->delete();

        return response()->json(null, 204);
    }
}
