<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDidRequest;
use App\Http\Requests\UpdateDidRequest;
use App\Http\Resources\DidResource;
use App\Models\Did;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing DIDs scoped to a tenant.
 */
class DidController extends Controller
{
    /**
     * List DIDs for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        $this->authorize('viewAny', Did::class);

        return DidResource::collection($tenant->dids()->paginate(15));
    }

    /**
     * Create a new DID for a tenant.
     */
    public function store(StoreDidRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', Did::class);

        if ($tenant->max_dids > 0 && $tenant->dids()->count() >= $tenant->max_dids) {
            return response()->json([
                'message' => 'DID quota exceeded. Maximum allowed: '.$tenant->max_dids,
            ], 422);
        }

        $did = $tenant->dids()->create($request->validated());

        return (new DidResource($did))->response()->setStatusCode(201);
    }

    /**
     * Show a single DID.
     */
    public function show(Tenant $tenant, Did $did): JsonResponse|DidResource
    {
        if ($did->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        $this->authorize('view', $did);

        return new DidResource($did);
    }

    /**
     * Update an existing DID.
     */
    public function update(UpdateDidRequest $request, Tenant $tenant, Did $did): JsonResponse|DidResource
    {
        if ($did->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        $this->authorize('update', $did);

        $did->update($request->validated());

        return new DidResource($did);
    }

    /**
     * Delete a DID.
     */
    public function destroy(Tenant $tenant, Did $did): JsonResponse
    {
        if ($did->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        $this->authorize('delete', $did);

        $did->delete();

        return response()->json(null, 204);
    }
}
