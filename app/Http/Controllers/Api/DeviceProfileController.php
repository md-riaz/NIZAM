<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceProfileRequest;
use App\Http\Requests\UpdateDeviceProfileRequest;
use App\Http\Resources\DeviceProfileResource;
use App\Models\DeviceProfile;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing device profiles scoped to a tenant.
 */
class DeviceProfileController extends Controller
{
    /**
     * List device profiles for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        $this->authorize('viewAny', DeviceProfile::class);

        return DeviceProfileResource::collection($tenant->deviceProfiles()->paginate(15));
    }

    /**
     * Create a new device profile for a tenant.
     */
    public function store(StoreDeviceProfileRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', DeviceProfile::class);

        $deviceProfile = $tenant->deviceProfiles()->create($request->validated());

        return (new DeviceProfileResource($deviceProfile))->response()->setStatusCode(201);
    }

    /**
     * Show a single device profile.
     */
    public function show(Tenant $tenant, DeviceProfile $deviceProfile): JsonResponse|DeviceProfileResource
    {
        if ($deviceProfile->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Device profile not found.'], 404);
        }

        $this->authorize('view', $deviceProfile);

        return new DeviceProfileResource($deviceProfile);
    }

    /**
     * Update an existing device profile.
     */
    public function update(UpdateDeviceProfileRequest $request, Tenant $tenant, DeviceProfile $deviceProfile): JsonResponse|DeviceProfileResource
    {
        if ($deviceProfile->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Device profile not found.'], 404);
        }

        $this->authorize('update', $deviceProfile);

        $deviceProfile->update($request->validated());

        return new DeviceProfileResource($deviceProfile);
    }

    /**
     * Delete a device profile.
     */
    public function destroy(Tenant $tenant, DeviceProfile $deviceProfile): JsonResponse
    {
        if ($deviceProfile->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Device profile not found.'], 404);
        }

        $this->authorize('delete', $deviceProfile);

        $deviceProfile->delete();

        return response()->json(null, 204);
    }
}
