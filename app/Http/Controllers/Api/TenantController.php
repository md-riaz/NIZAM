<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
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
    public function index(): JsonResponse
    {
        $tenants = Tenant::paginate(15);

        return response()->json($tenants);
    }

    /**
     * Create a new tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants',
            'slug' => 'required|string|unique:tenants|alpha_dash',
            'max_extensions' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $tenant = Tenant::create($validated);

        return response()->json($tenant, 201);
    }

    /**
     * Show a single tenant.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json($tenant);
    }

    /**
     * Update an existing tenant.
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants,domain,' . $tenant->id,
            'slug' => 'required|string|alpha_dash|unique:tenants,slug,' . $tenant->id,
            'max_extensions' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $tenant->update($validated);

        return response()->json($tenant);
    }

    /**
     * Delete a tenant.
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return response()->json(null, 204);
    }
}
