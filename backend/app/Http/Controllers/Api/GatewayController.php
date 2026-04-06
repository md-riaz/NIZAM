<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGatewayRequest;
use App\Http\Requests\UpdateGatewayRequest;
use App\Http\Resources\GatewayResource;
use App\Models\Gateway;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing SIP gateways scoped to a tenant.
 */
class GatewayController extends Controller
{
    /**
     * List gateways for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        $this->authorize('viewAny', Gateway::class);

        return GatewayResource::collection($tenant->gateways()->paginate(15));
    }

    /**
     * Create a new gateway for a tenant.
     */
    public function store(StoreGatewayRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', Gateway::class);

        $gateway = $tenant->gateways()->create($request->validated());

        return (new GatewayResource($gateway))->response()->setStatusCode(201);
    }

    /**
     * Show a single gateway.
     */
    public function show(Tenant $tenant, Gateway $gateway): JsonResponse|GatewayResource
    {
        if ($gateway->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Gateway not found.'], 404);
        }

        $this->authorize('view', $gateway);

        return new GatewayResource($gateway);
    }

    /**
     * Update an existing gateway.
     */
    public function update(UpdateGatewayRequest $request, Tenant $tenant, Gateway $gateway): JsonResponse|GatewayResource
    {
        if ($gateway->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Gateway not found.'], 404);
        }

        $this->authorize('update', $gateway);

        $gateway->update($request->validated());

        return new GatewayResource($gateway);
    }

    /**
     * Delete a gateway.
     */
    public function destroy(Tenant $tenant, Gateway $gateway): JsonResponse
    {
        if ($gateway->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Gateway not found.'], 404);
        }

        $this->authorize('delete', $gateway);

        $gateway->delete();

        return response()->json(null, 204);
    }
}
