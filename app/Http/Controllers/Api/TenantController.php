<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing tenants.
 */
class TenantController extends Controller
{
    /**
     * List all tenants (paginated).
     */
    public function index()
    {
        $this->authorize('viewAny', Tenant::class);

        $user = request()->user();

        // Non-admin users only see their own tenant
        if ($user->role !== 'admin') {
            return TenantResource::collection(
                Tenant::where('id', $user->tenant_id)->paginate(15)
            );
        }

        return TenantResource::collection(Tenant::paginate(15));
    }

    /**
     * Create a new tenant.
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $this->authorize('create', Tenant::class);

        $tenant = Tenant::create($request->validated());

        return (new TenantResource($tenant))->response()->setStatusCode(201);
    }

    /**
     * Show a single tenant.
     */
    public function show(Tenant $tenant): TenantResource
    {
        $this->authorize('view', $tenant);

        return new TenantResource($tenant);
    }

    /**
     * Update an existing tenant.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        $this->authorize('update', $tenant);

        $tenant->update($request->validated());

        return new TenantResource($tenant);
    }

    /**
     * Delete a tenant.
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->authorize('delete', $tenant);

        $tenant->delete();

        return response()->json(null, 204);
    }
}
