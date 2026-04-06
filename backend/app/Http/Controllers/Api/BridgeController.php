<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBridgeRequest;
use App\Http\Requests\UpdateBridgeRequest;
use App\Http\Resources\BridgeResource;
use App\Models\Bridge;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class BridgeController extends Controller
{
    public function index(Tenant $tenant)
    {
        $this->authorize('viewAny', Bridge::class);

        return BridgeResource::collection($tenant->bridges()->paginate(15));
    }

    public function store(StoreBridgeRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', Bridge::class);

        $bridge = $tenant->bridges()->create($request->validated());

        return (new BridgeResource($bridge))->response()->setStatusCode(201);
    }

    public function show(Tenant $tenant, Bridge $bridge): JsonResponse|BridgeResource
    {
        if ($bridge->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Bridge not found.'], 404);
        }

        $this->authorize('view', $bridge);

        return new BridgeResource($bridge);
    }

    public function update(UpdateBridgeRequest $request, Tenant $tenant, Bridge $bridge): JsonResponse|BridgeResource
    {
        if ($bridge->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Bridge not found.'], 404);
        }

        $this->authorize('update', $bridge);

        $bridge->update($request->validated());

        return new BridgeResource($bridge);
    }

    public function destroy(Tenant $tenant, Bridge $bridge): JsonResponse
    {
        if ($bridge->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Bridge not found.'], 404);
        }

        $this->authorize('delete', $bridge);

        $bridge->delete();

        return response()->json(null, 204);
    }
}
