<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBridgeRequest;
use App\Http\Requests\UpdateBridgeRequest;
use App\Http\Resources\BridgeResource;
use App\Models\Bridge;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class BridgeController extends Controller
{
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', Bridge::class);

        return BridgeResource::collection($organization->bridges()->paginate(15));
    }

    public function store(StoreBridgeRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', Bridge::class);

        $bridge = $organization->bridges()->create($request->validated());

        return (new BridgeResource($bridge))->response()->setStatusCode(201);
    }

    public function show(Organization $organization, Bridge $bridge): JsonResponse|BridgeResource
    {
        if ($bridge->organization_id !== $organization->id) {
            return response()->json(['message' => 'Bridge not found.'], 404);
        }

        $this->authorize('view', $bridge);

        return new BridgeResource($bridge);
    }

    public function update(UpdateBridgeRequest $request, Organization $organization, Bridge $bridge): JsonResponse|BridgeResource
    {
        if ($bridge->organization_id !== $organization->id) {
            return response()->json(['message' => 'Bridge not found.'], 404);
        }

        $this->authorize('update', $bridge);

        $bridge->update($request->validated());

        return new BridgeResource($bridge);
    }

    public function destroy(Organization $organization, Bridge $bridge): JsonResponse
    {
        if ($bridge->organization_id !== $organization->id) {
            return response()->json(['message' => 'Bridge not found.'], 404);
        }

        $this->authorize('delete', $bridge);

        $bridge->delete();

        return response()->json(null, 204);
    }
}
