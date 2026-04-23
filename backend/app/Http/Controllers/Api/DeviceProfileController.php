<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceProfileRequest;
use App\Http\Requests\UpdateDeviceProfileRequest;
use App\Http\Resources\DeviceProfileResource;
use App\Models\DeviceProfile;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for managing device profiles scoped to a organization.
 */
class DeviceProfileController extends Controller
{
    /**
     * List device profiles for an organization (paginated).
     */
    public function index(Request $request, Organization $organization)
    {
        $this->authorize('viewAny', DeviceProfile::class);

        $perPage = max(1, min($request->integer('per_page', 15), 500));

        return DeviceProfileResource::collection(
            $organization->deviceProfiles()->with('phoneNumbers')->orderByDesc('id')->paginate($perPage)
        );
    }

    /**
     * Create a new device profile for an organization.
     */
    public function store(StoreDeviceProfileRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', DeviceProfile::class);

        $validated = $request->validated();
        $deviceProfile = $organization->deviceProfiles()->create(collect($validated)->except(['phone_number_ids'])->all());
        $deviceProfile->phoneNumbers()->sync($validated['phone_number_ids'] ?? []);

        return (new DeviceProfileResource($deviceProfile->load('phoneNumbers')))->response()->setStatusCode(201);
    }

    /**
     * Show a single device profile.
     */
    public function show(Organization $organization, DeviceProfile $deviceProfile): JsonResponse|DeviceProfileResource
    {
        if ($deviceProfile->organization_id !== $organization->id) {
            return response()->json(['message' => 'Device profile not found.'], 404);
        }

        $this->authorize('view', $deviceProfile);

        return new DeviceProfileResource($deviceProfile->load('phoneNumbers'));
    }

    /**
     * Update an existing device profile.
     */
    public function update(UpdateDeviceProfileRequest $request, Organization $organization, DeviceProfile $deviceProfile): JsonResponse|DeviceProfileResource
    {
        if ($deviceProfile->organization_id !== $organization->id) {
            return response()->json(['message' => 'Device profile not found.'], 404);
        }

        $this->authorize('update', $deviceProfile);

        $validated = $request->validated();
        $deviceProfile->update(collect($validated)->except(['phone_number_ids'])->all());
        $deviceProfile->phoneNumbers()->sync($validated['phone_number_ids'] ?? []);

        return new DeviceProfileResource($deviceProfile->load('phoneNumbers'));
    }

    /**
     * Delete a device profile.
     */
    public function destroy(Organization $organization, DeviceProfile $deviceProfile): JsonResponse
    {
        if ($deviceProfile->organization_id !== $organization->id) {
            return response()->json(['message' => 'Device profile not found.'], 404);
        }

        $this->authorize('delete', $deviceProfile);

        $deviceProfile->delete();

        return response()->json(null, 204);
    }
}
