<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGatewayRequest;
use App\Http\Requests\UpdateGatewayRequest;
use App\Http\Resources\GatewayResource;
use App\Models\Gateway;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing SIP gateways scoped to a organization.
 */
class GatewayController extends Controller
{
    /**
     * List gateways for an organization (paginated).
     */
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', Gateway::class);

        return GatewayResource::collection($organization->gateways()->orderByDesc('id')->paginate(15));
    }

    /**
     * Create a new gateway for an organization.
     */
    public function store(StoreGatewayRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', Gateway::class);

        $gateway = $organization->gateways()->create($request->validated());

        return (new GatewayResource($gateway))->response()->setStatusCode(201);
    }

    /**
     * Show a single gateway.
     */
    public function show(Organization $organization, Gateway $gateway): JsonResponse|GatewayResource
    {
        if ($gateway->organization_id !== $organization->id) {
            return response()->json(['message' => 'Gateway not found.'], 404);
        }

        $this->authorize('view', $gateway);

        return new GatewayResource($gateway);
    }

    /**
     * Update an existing gateway.
     */
    public function update(UpdateGatewayRequest $request, Organization $organization, Gateway $gateway): JsonResponse|GatewayResource
    {
        if ($gateway->organization_id !== $organization->id) {
            return response()->json(['message' => 'Gateway not found.'], 404);
        }

        $this->authorize('update', $gateway);

        $gateway->update($request->validated());

        return new GatewayResource($gateway);
    }

    /**
     * Delete a gateway.
     */
    public function destroy(Organization $organization, Gateway $gateway): JsonResponse
    {
        if ($gateway->organization_id !== $organization->id) {
            return response()->json(['message' => 'Gateway not found.'], 404);
        }

        $this->authorize('delete', $gateway);

        $gateway->delete();

        return response()->json(null, 204);
    }
}
