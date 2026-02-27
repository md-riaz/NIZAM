<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceProfile;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for managing device profiles scoped to a tenant.
 */
class DeviceProfileController extends Controller
{
    /**
     * List device profiles for a tenant (paginated).
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $deviceProfiles = $tenant->deviceProfiles()->paginate(15);

        return response()->json($deviceProfiles);
    }

    /**
     * Create a new device profile for a tenant.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'vendor' => 'nullable|string',
            'mac_address' => 'nullable|string',
            'template' => 'nullable|string',
            'extension_id' => 'nullable|uuid',
            'is_active' => 'boolean',
        ]);

        $deviceProfile = $tenant->deviceProfiles()->create($validated);

        return response()->json($deviceProfile, 201);
    }

    /**
     * Show a single device profile.
     */
    public function show(Tenant $tenant, DeviceProfile $deviceProfile): JsonResponse
    {
        if ($deviceProfile->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Device profile not found.'], 404);
        }

        return response()->json($deviceProfile);
    }

    /**
     * Update an existing device profile.
     */
    public function update(Request $request, Tenant $tenant, DeviceProfile $deviceProfile): JsonResponse
    {
        if ($deviceProfile->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Device profile not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'vendor' => 'nullable|string',
            'mac_address' => 'nullable|string',
            'template' => 'nullable|string',
            'extension_id' => 'nullable|uuid',
            'is_active' => 'boolean',
        ]);

        $deviceProfile->update($validated);

        return response()->json($deviceProfile);
    }

    /**
     * Delete a device profile.
     */
    public function destroy(Tenant $tenant, DeviceProfile $deviceProfile): JsonResponse
    {
        if ($deviceProfile->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Device profile not found.'], 404);
        }

        $deviceProfile->delete();

        return response()->json(null, 204);
    }
}
