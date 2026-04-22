<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIvrRequest;
use App\Http\Requests\UpdateIvrRequest;
use App\Http\Resources\IvrResource;
use App\Models\Ivr;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing IVRs scoped to a organization.
 */
class IvrController extends Controller
{
    /**
     * List IVRs for an organization (paginated).
     */
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', Ivr::class);

        return IvrResource::collection($organization->ivrs()->orderByDesc('id')->paginate(15));
    }

    /**
     * Create a new IVR for an organization.
     */
    public function store(StoreIvrRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', Ivr::class);

        $ivr = $organization->ivrs()->create($request->validated());

        return (new IvrResource($ivr))->response()->setStatusCode(201);
    }

    /**
     * Show a single IVR.
     */
    public function show(Organization $organization, Ivr $ivr): JsonResponse|IvrResource
    {
        if ($ivr->organization_id !== $organization->id) {
            return response()->json(['message' => 'IVR not found.'], 404);
        }

        $this->authorize('view', $ivr);

        return new IvrResource($ivr);
    }

    /**
     * Update an existing IVR.
     */
    public function update(UpdateIvrRequest $request, Organization $organization, Ivr $ivr): JsonResponse|IvrResource
    {
        if ($ivr->organization_id !== $organization->id) {
            return response()->json(['message' => 'IVR not found.'], 404);
        }

        $this->authorize('update', $ivr);

        $ivr->update($request->validated());

        return new IvrResource($ivr);
    }

    /**
     * Delete an IVR.
     */
    public function destroy(Organization $organization, Ivr $ivr): JsonResponse
    {
        if ($ivr->organization_id !== $organization->id) {
            return response()->json(['message' => 'IVR not found.'], 404);
        }

        $this->authorize('delete', $ivr);

        $ivr->delete();

        return response()->json(null, 204);
    }
}
