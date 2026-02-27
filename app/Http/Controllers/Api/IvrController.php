<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIvrRequest;
use App\Http\Requests\UpdateIvrRequest;
use App\Http\Resources\IvrResource;
use App\Models\Ivr;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing IVRs scoped to a tenant.
 */
class IvrController extends Controller
{
    /**
     * List IVRs for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        return IvrResource::collection($tenant->ivrs()->paginate(15));
    }

    /**
     * Create a new IVR for a tenant.
     */
    public function store(StoreIvrRequest $request, Tenant $tenant): JsonResponse
    {
        $ivr = $tenant->ivrs()->create($request->validated());

        return (new IvrResource($ivr))->response()->setStatusCode(201);
    }

    /**
     * Show a single IVR.
     */
    public function show(Tenant $tenant, Ivr $ivr): JsonResponse|IvrResource
    {
        if ($ivr->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'IVR not found.'], 404);
        }

        return new IvrResource($ivr);
    }

    /**
     * Update an existing IVR.
     */
    public function update(UpdateIvrRequest $request, Tenant $tenant, Ivr $ivr): JsonResponse|IvrResource
    {
        if ($ivr->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'IVR not found.'], 404);
        }

        $ivr->update($request->validated());

        return new IvrResource($ivr);
    }

    /**
     * Delete an IVR.
     */
    public function destroy(Tenant $tenant, Ivr $ivr): JsonResponse
    {
        if ($ivr->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'IVR not found.'], 404);
        }

        $ivr->delete();

        return response()->json(null, 204);
    }
}
