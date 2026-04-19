<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDidRequest;
use App\Http\Requests\UpdateDidRequest;
use App\Http\Resources\DidResource;
use App\Models\Did;
use App\Models\Organization;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing DIDs scoped to a organization.
 */
class DidController extends Controller
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher
    ) {}

    /**
     * List DIDs for an organization (paginated).
     */
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', Did::class);

        return DidResource::collection($organization->dids()->with('gateway')->paginate(15));
    }

    /**
     * Create a new DID for an organization.
     */
    public function store(StoreDidRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', Did::class);

        if ($organization->max_dids > 0 && $organization->dids()->count() >= $organization->max_dids) {
            return response()->json([
                'message' => 'DID quota exceeded. Maximum allowed: '.$organization->max_dids,
            ], 422);
        }

        $did = $organization->dids()->create($request->validated());

        $this->webhookDispatcher->dispatch($organization->id, 'did.created', [
            'did_id' => $did->id,
            'number' => $did->number,
        ]);

        return (new DidResource($did))->response()->setStatusCode(201);
    }

    /**
     * Show a single DID.
     */
    public function show(Organization $organization, Did $did): JsonResponse|DidResource
    {
        if ($did->organization_id !== $organization->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        $this->authorize('view', $did);

        $did->loadMissing('gateway');

        return new DidResource($did);
    }

    /**
     * Update an existing DID.
     */
    public function update(UpdateDidRequest $request, Organization $organization, Did $did): JsonResponse|DidResource
    {
        if ($did->organization_id !== $organization->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        $this->authorize('update', $did);

        $did->update($request->validated());

        $this->webhookDispatcher->dispatch($organization->id, 'did.updated', [
            'did_id' => $did->id,
            'number' => $did->number,
        ]);

        $did->loadMissing('gateway');

        return new DidResource($did);
    }

    /**
     * Delete a DID.
     */
    public function destroy(Organization $organization, Did $did): JsonResponse
    {
        if ($did->organization_id !== $organization->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        $this->authorize('delete', $did);

        $didNumber = $did->number;
        $didId = $did->id;
        $did->delete();

        $this->webhookDispatcher->dispatch($organization->id, 'did.deleted', [
            'did_id' => $didId,
            'number' => $didNumber,
        ]);

        return response()->json(null, 204);
    }
}
