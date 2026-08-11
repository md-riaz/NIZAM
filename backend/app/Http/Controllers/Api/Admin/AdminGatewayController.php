<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGatewayRequest;
use App\Http\Requests\UpdateGatewayRequest;
use App\Http\Resources\GatewayResource;
use App\Models\Gateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * API controller for managing SIP gateways globally across all organizations.
 *
 * This controller deliberately crosses organization boundaries: index lists
 * every tenant's gateways and store accepts an arbitrary organization_id. It is
 * therefore restricted to platform admins. Organization-scoped gateway access
 * belongs on Api\GatewayController, which is nested under
 * organizations/{organization} and filtered accordingly.
 */
class AdminGatewayController extends Controller
{
    /**
     * List all gateways across all organizations.
     */
    public function index()
    {
        Gate::authorize('platform-admin');
        $this->authorize('viewAny', Gateway::class);

        return GatewayResource::collection(Gateway::with('organization')->orderByDesc('id')->paginate(20));
    }

    /**
     * Create a new gateway globally.
     */
    public function store(StoreGatewayRequest $request): JsonResponse
    {
        Gate::authorize('platform-admin');
        $this->authorize('create', Gateway::class);

        // Required logic requires organization_id from the JSON payload.
        $gateway = Gateway::create($request->validated());

        return (new GatewayResource($gateway))->response()->setStatusCode(201);
    }

    /**
     * Show a single gateway globally.
     */
    public function show(Gateway $gateway): JsonResponse|GatewayResource
    {
        Gate::authorize('platform-admin');
        $this->authorize('view', $gateway);

        return new GatewayResource($gateway);
    }

    /**
     * Update an existing gateway globally.
     */
    public function update(UpdateGatewayRequest $request, Gateway $gateway): JsonResponse|GatewayResource
    {
        Gate::authorize('platform-admin');
        $this->authorize('update', $gateway);

        $gateway->update($request->validated());

        return new GatewayResource($gateway);
    }

    /**
     * Delete a gateway globally.
     */
    public function destroy(Gateway $gateway): JsonResponse
    {
        Gate::authorize('platform-admin');
        $this->authorize('delete', $gateway);

        $gateway->delete();

        return response()->json(null, 204);
    }
}
