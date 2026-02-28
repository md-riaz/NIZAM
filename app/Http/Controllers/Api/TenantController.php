<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * Get tenant settings.
     */
    public function settings(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        return response()->json([
            'data' => $tenant->settings ?? [],
        ]);
    }

    /**
     * Merge-update tenant settings.
     */
    public function updateSettings(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        $tenant->update([
            'settings' => array_merge($tenant->settings ?? [], $validated['settings']),
        ]);

        return response()->json([
            'data' => $tenant->settings,
        ]);
    }

    /**
     * Provision a new tenant with zero-touch onboarding.
     */
    public function provision(Request $request, TenantProvisioningService $provisioning): JsonResponse
    {
        $this->authorize('create', Tenant::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|unique:tenants',
            'slug' => 'nullable|string|unique:tenants|alpha_dash',
            'max_extensions' => 'integer|min:0',
            'max_concurrent_calls' => 'integer|min:0',
            'max_dids' => 'integer|min:0',
            'max_ring_groups' => 'integer|min:0',
            'settings' => 'array',
        ]);

        $result = $provisioning->provision($validated);

        return response()->json([
            'data' => [
                'tenant' => new TenantResource($result['tenant']),
                'default_extension' => [
                    'extension' => $result['default_extension']->extension,
                ],
                'provisioning_domain' => $result['provisioning_domain'],
            ],
        ], 201);
    }
}
