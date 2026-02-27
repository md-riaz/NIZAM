<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ivr;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for managing IVRs scoped to a tenant.
 */
class IvrController extends Controller
{
    /**
     * List IVRs for a tenant (paginated).
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $ivrs = $tenant->ivrs()->paginate(15);

        return response()->json($ivrs);
    }

    /**
     * Create a new IVR for a tenant.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'greet_long' => 'nullable|string',
            'greet_short' => 'nullable|string',
            'timeout' => 'integer|min:1|max:60',
            'max_failures' => 'integer|min:1|max:10',
            'options' => 'required|array',
            'timeout_destination_type' => 'nullable|string|required_with:timeout_destination_id',
            'timeout_destination_id' => 'nullable|uuid|required_with:timeout_destination_type',
            'is_active' => 'boolean',
        ]);

        $ivr = $tenant->ivrs()->create($validated);

        return response()->json($ivr, 201);
    }

    /**
     * Show a single IVR.
     */
    public function show(Tenant $tenant, Ivr $ivr): JsonResponse
    {
        if ($ivr->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'IVR not found.'], 404);
        }

        return response()->json($ivr);
    }

    /**
     * Update an existing IVR.
     */
    public function update(Request $request, Tenant $tenant, Ivr $ivr): JsonResponse
    {
        if ($ivr->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'IVR not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'greet_long' => 'nullable|string',
            'greet_short' => 'nullable|string',
            'timeout' => 'integer|min:1|max:60',
            'max_failures' => 'integer|min:1|max:10',
            'options' => 'required|array',
            'timeout_destination_type' => 'nullable|string|required_with:timeout_destination_id',
            'timeout_destination_id' => 'nullable|uuid|required_with:timeout_destination_type',
            'is_active' => 'boolean',
        ]);

        $ivr->update($validated);

        return response()->json($ivr);
    }

    /**
     * Delete an IVR.
     */
    public function destroy(Tenant $tenant, Ivr $ivr): JsonResponse
    {
        if ($ivr->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'IVR not found.'], 404);
        }

        $ivr->delete();

        return response()->json(null, 204);
    }
}
