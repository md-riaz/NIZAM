<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Did;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for managing DIDs scoped to a tenant.
 */
class DidController extends Controller
{
    /**
     * List DIDs for a tenant (paginated).
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $dids = $tenant->dids()->paginate(15);

        return response()->json($dids);
    }

    /**
     * Create a new DID for a tenant.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'number' => 'required|string',
            'description' => 'nullable|string',
            'destination_type' => 'required|in:extension,ring_group,ivr,time_condition,voicemail',
            'destination_id' => 'required|uuid',
            'is_active' => 'boolean',
        ]);

        $did = $tenant->dids()->create($validated);

        return response()->json($did, 201);
    }

    /**
     * Show a single DID.
     */
    public function show(Tenant $tenant, Did $did): JsonResponse
    {
        if ($did->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        return response()->json($did);
    }

    /**
     * Update an existing DID.
     */
    public function update(Request $request, Tenant $tenant, Did $did): JsonResponse
    {
        if ($did->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        $validated = $request->validate([
            'number' => 'required|string',
            'description' => 'nullable|string',
            'destination_type' => 'required|in:extension,ring_group,ivr,time_condition,voicemail',
            'destination_id' => 'required|uuid',
            'is_active' => 'boolean',
        ]);

        $did->update($validated);

        return response()->json($did);
    }

    /**
     * Delete a DID.
     */
    public function destroy(Tenant $tenant, Did $did): JsonResponse
    {
        if ($did->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'DID not found.'], 404);
        }

        $did->delete();

        return response()->json(null, 204);
    }
}
