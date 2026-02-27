<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RingGroup;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for managing ring groups scoped to a tenant.
 */
class RingGroupController extends Controller
{
    /**
     * List ring groups for a tenant (paginated).
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $ringGroups = $tenant->ringGroups()->paginate(15);

        return response()->json($ringGroups);
    }

    /**
     * Create a new ring group for a tenant.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'strategy' => 'in:simultaneous,sequential',
            'ring_timeout' => 'integer|min:5|max:300',
            'members' => 'required|array',
            'fallback_destination_type' => 'nullable|in:extension,ring_group,ivr,time_condition,voicemail|required_with:fallback_destination_id',
            'fallback_destination_id' => 'nullable|uuid|required_with:fallback_destination_type',
            'is_active' => 'boolean',
        ]);

        $ringGroup = $tenant->ringGroups()->create($validated);

        return response()->json($ringGroup, 201);
    }

    /**
     * Show a single ring group.
     */
    public function show(Tenant $tenant, RingGroup $ringGroup): JsonResponse
    {
        if ($ringGroup->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Ring group not found.'], 404);
        }

        return response()->json($ringGroup);
    }

    /**
     * Update an existing ring group.
     */
    public function update(Request $request, Tenant $tenant, RingGroup $ringGroup): JsonResponse
    {
        if ($ringGroup->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Ring group not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'strategy' => 'in:simultaneous,sequential',
            'ring_timeout' => 'integer|min:5|max:300',
            'members' => 'required|array',
            'fallback_destination_type' => 'nullable|in:extension,ring_group,ivr,time_condition,voicemail|required_with:fallback_destination_id',
            'fallback_destination_id' => 'nullable|uuid|required_with:fallback_destination_type',
            'is_active' => 'boolean',
        ]);

        $ringGroup->update($validated);

        return response()->json($ringGroup);
    }

    /**
     * Delete a ring group.
     */
    public function destroy(Tenant $tenant, RingGroup $ringGroup): JsonResponse
    {
        if ($ringGroup->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Ring group not found.'], 404);
        }

        $ringGroup->delete();

        return response()->json(null, 204);
    }
}
