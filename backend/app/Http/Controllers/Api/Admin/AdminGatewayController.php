<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGatewayRequest;
use App\Http\Requests\UpdateGatewayRequest;
use App\Http\Resources\GatewayResource;
use App\Models\Gateway;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing SIP gateways globally across all tenants.
 */
class AdminGatewayController extends Controller
{
    /**
     * List all gateways across all tenants.
     */
    public function index()
    {
        $this->authorize('viewAny', Gateway::class);

        return GatewayResource::collection(Gateway::with('tenant')->paginate(20));
    }

    /**
     * Create a new gateway globally.
     */
    public function store(StoreGatewayRequest $request): JsonResponse
    {
        $this->authorize('create', Gateway::class);

        // Required logic requires tenant_id from the JSON payload.
        $gateway = Gateway::create($request->validated());

        return (new GatewayResource($gateway))->response()->setStatusCode(201);
    }

    /**
     * Show a single gateway globally.
     */
    public function show(Gateway $gateway): JsonResponse|GatewayResource
    {
        $this->authorize('view', $gateway);

        return new GatewayResource($gateway);
    }

    /**
     * Update an existing gateway globally.
     */
    public function update(UpdateGatewayRequest $request, Gateway $gateway): JsonResponse|GatewayResource
    {
        $this->authorize('update', $gateway);

        $gateway->update($request->validated());

        return new GatewayResource($gateway);
    }

    /**
     * Delete a gateway globally.
     */
    public function destroy(Gateway $gateway): JsonResponse
    {
        $this->authorize('delete', $gateway);

        $gateway->delete();

        return response()->json(null, 204);
    }
}
