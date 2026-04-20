<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileDeviceHeartbeatRequest;
use App\Http\Requests\RefreshMobileDeviceTokenRequest;
use App\Http\Requests\RegisterMobileDeviceRequest;
use App\Http\Requests\UpdateMobileDeviceCapabilitiesRequest;
use App\Http\Requests\UpdateMobileDeviceRequest;
use App\Http\Resources\MobileDeviceResource;
use App\Models\EndpointBinding;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class MobileDeviceController extends Controller
{
    public function register(RegisterMobileDeviceRequest $request, Organization $organization): JsonResponse
    {
        $binding = $organization->endpointBindings()->firstOrNew([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'device_uuid' => $request->validated('device_uuid'),
        ]);

        $this->authorize($binding->exists ? 'update' : 'create', $binding->exists ? $binding : EndpointBinding::class);

        $this->applyPayload($binding, $request->validated(), touchLastSeen: true, setDefaults: ! $binding->exists);
        $binding->organization()->associate($organization);
        $binding->type = EndpointBinding::TYPE_MOBILE_APP;
        $binding->save();
        $binding->load('extension');

        return (new MobileDeviceResource($binding))
            ->response()
            ->setStatusCode($binding->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateMobileDeviceRequest $request, Organization $organization, EndpointBinding $endpointBinding): JsonResponse|MobileDeviceResource
    {
        if ($response = $this->ensureScopedMobileDevice($organization, $endpointBinding)) {
            return $response;
        }

        $this->authorize('update', $endpointBinding);

        $this->applyPayload($endpointBinding, $request->validated());
        $endpointBinding->save();
        $endpointBinding->load('extension');

        return new MobileDeviceResource($endpointBinding);
    }

    public function destroy(Organization $organization, EndpointBinding $endpointBinding): JsonResponse
    {
        if ($response = $this->ensureScopedMobileDevice($organization, $endpointBinding)) {
            return $response;
        }

        $this->authorize('delete', $endpointBinding);

        $endpointBinding->delete();

        return response()->json(null, 204);
    }

    public function refreshToken(RefreshMobileDeviceTokenRequest $request, Organization $organization, EndpointBinding $endpointBinding): JsonResponse|MobileDeviceResource
    {
        if ($response = $this->ensureScopedMobileDevice($organization, $endpointBinding)) {
            return $response;
        }

        $this->authorize('update', $endpointBinding);

        $this->applyPayload($endpointBinding, $request->validated(), touchLastSeen: true);
        $endpointBinding->save();
        $endpointBinding->load('extension');

        return new MobileDeviceResource($endpointBinding);
    }

    public function heartbeat(MobileDeviceHeartbeatRequest $request, Organization $organization, EndpointBinding $endpointBinding): JsonResponse|MobileDeviceResource
    {
        if ($response = $this->ensureScopedMobileDevice($organization, $endpointBinding)) {
            return $response;
        }

        $this->authorize('update', $endpointBinding);

        $payload = $request->validated();
        $this->applyPayload($endpointBinding, $payload);
        $endpointBinding->last_seen_at = isset($payload['last_seen_at'])
            ? Carbon::parse($payload['last_seen_at'])
            : now();
        $endpointBinding->save();
        $endpointBinding->load('extension');

        return new MobileDeviceResource($endpointBinding);
    }

    public function capabilities(UpdateMobileDeviceCapabilitiesRequest $request, Organization $organization, EndpointBinding $endpointBinding): JsonResponse|MobileDeviceResource
    {
        if ($response = $this->ensureScopedMobileDevice($organization, $endpointBinding)) {
            return $response;
        }

        $this->authorize('update', $endpointBinding);

        $this->applyPayload($endpointBinding, $request->validated(), touchLastSeen: true);
        $endpointBinding->save();
        $endpointBinding->load('extension');

        return new MobileDeviceResource($endpointBinding);
    }

    private function ensureScopedMobileDevice(Organization $organization, EndpointBinding $endpointBinding): ?JsonResponse
    {
        if ($endpointBinding->organization_id !== $organization->id || $endpointBinding->type !== EndpointBinding::TYPE_MOBILE_APP) {
            return response()->json(['message' => 'Mobile device not found.'], 404);
        }

        return null;
    }

    private function applyPayload(EndpointBinding $binding, array $payload, bool $touchLastSeen = false, bool $setDefaults = false): void
    {
        if ($setDefaults) {
            $binding->is_enabled = $payload['is_enabled'] ?? true;
            $binding->rings_immediately_when_online = $payload['sip_background_mode_supported'] ?? true;
            $binding->allow_late_join_after_push = $payload['allow_late_join_after_push'] ?? false;
        }

        foreach (['extension_id', 'platform', 'app_version', 'device_uuid', 'push_token', 'voip_push_token'] as $field) {
            if (array_key_exists($field, $payload)) {
                $binding->{$field} = $payload[$field];
            }
        }

        if (array_key_exists('is_enabled', $payload)) {
            $binding->is_enabled = (bool) $payload['is_enabled'];
        }

        if (array_key_exists('sip_background_mode_supported', $payload)) {
            $binding->rings_immediately_when_online = (bool) $payload['sip_background_mode_supported'];
        }

        if (array_key_exists('allow_late_join_after_push', $payload)) {
            $binding->allow_late_join_after_push = (bool) $payload['allow_late_join_after_push'];
        }

        if (array_key_exists('last_registered_at', $payload)) {
            $binding->last_registered_at = $payload['last_registered_at']
                ? Carbon::parse($payload['last_registered_at'])
                : null;
        }

        if ($touchLastSeen) {
            $binding->last_seen_at = now();
        }

        $metadata = $binding->metadata ?? [];
        if (array_key_exists('push_enabled', $payload)) {
            $metadata['push_enabled'] = (bool) $payload['push_enabled'];
        }
        if (array_key_exists('sip_background_mode_supported', $payload)) {
            $metadata['sip_background_mode_supported'] = (bool) $payload['sip_background_mode_supported'];
        }

        $binding->metadata = $metadata;
        $binding->is_push_capable = $binding->pushEnabled() && $binding->hasPushTokenMaterial();

        $this->ensureRuntimeCapabilitiesAreValid($binding);
    }

    private function ensureRuntimeCapabilitiesAreValid(EndpointBinding $binding): void
    {
        if ($binding->is_push_capable && ! $binding->hasPushTokenMaterial()) {
            throw new HttpResponseException(response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'push_token' => ['Push-capable endpoints require push token material.'],
                ],
            ], 422));
        }
    }
}
