<?php

namespace App\Http\Controllers\Api;

use App\Data\ExtensionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExtensionRequest;
use App\Http\Requests\UpdateExtensionRequest;
use App\Http\Resources\ExtensionResource;
use App\Models\DeviceProfile;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\ExtensionFeatureService;
use App\Services\OrganizationManifestBuilder;
use App\Services\WebhookDispatcher;
use App\Services\WebRtcConfigService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * API controller for managing extensions scoped to a organization.
 */
class ExtensionController extends Controller
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher,
        protected WebRtcConfigService $webRtcConfigService,
        protected ExtensionFeatureService $extensionFeatureService,
        protected OrganizationManifestBuilder $manifestBuilder,
    ) {}

    /**
     * List extensions for an organization (paginated).
     */
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', Extension::class);

        return ExtensionResource::collection(
            $organization->extensions()
                ->with(['allowedOutboundDids:id', 'allowedOutboundGateways:id'])
                ->orderBy('extension')
                ->paginate(15)
        );
    }

    /**
     * Create a new extension for an organization.
     */
    public function store(StoreExtensionRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('create', Extension::class);

        if ($organization->max_extensions > 0 && $organization->extensions()->count() >= $organization->max_extensions) {
            return response()->json([
                'message' => 'Extension quota exceeded. Maximum allowed: '.$organization->max_extensions,
            ], 422);
        }

        $attributes = ExtensionData::fromArray($request->validated())->attributes;
        $featureAttributes = $this->extractFeatureAttributes($attributes);
        $allowedOutboundDidIds = $attributes['allowed_outbound_did_ids'] ?? [];
        $allowedOutboundGatewayIds = $attributes['allowed_outbound_gateway_ids'] ?? [];
        unset($attributes['allowed_outbound_did_ids'], $attributes['allowed_outbound_gateway_ids']);

        $extension = $organization->extensions()->create($attributes);

        try {
            if ($featureAttributes !== []) {
                $extension = $this->extensionFeatureService->updateFeatures($extension, $featureAttributes);
            }
        } catch (InvalidArgumentException $exception) {
            $extension->delete();

            return $this->featureValidationResponse($exception);
        }

        $this->syncOutboundPolicy($extension, $allowedOutboundDidIds, $allowedOutboundGatewayIds);
        if (($attributes['device_profile_id'] ?? null) !== null) {
            $this->syncOwnedDevice($extension, $attributes['device_profile_id']);
        }

        $this->webhookDispatcher->dispatch($organization->id, 'extension.created', [
            'extension_id' => $extension->id,
            'extension' => $extension->extension,
        ]);

        return (new ExtensionResource($extension->load(['allowedOutboundDids:id', 'allowedOutboundGateways:id'])))->response()->setStatusCode(201);
    }

    /**
     * Show a single extension.
     */
    public function show(Organization $organization, Extension $extension): JsonResponse|ExtensionResource
    {
        if ($extension->organization_id !== $organization->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $this->authorize('view', $extension);

        return new ExtensionResource($extension->load(['allowedOutboundDids:id', 'allowedOutboundGateways:id']));
    }

    /**
     * Update an existing extension.
     */
    public function update(UpdateExtensionRequest $request, Organization $organization, Extension $extension): JsonResponse|ExtensionResource
    {
        if ($extension->organization_id !== $organization->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $this->authorize('update', $extension);

        $attributes = ExtensionData::fromArray($request->validated())->attributes;
        $featureAttributes = $this->extractFeatureAttributes($attributes);
        $allowedOutboundDidIds = $attributes['allowed_outbound_did_ids'] ?? [];
        $allowedOutboundGatewayIds = $attributes['allowed_outbound_gateway_ids'] ?? [];
        unset($attributes['allowed_outbound_did_ids'], $attributes['allowed_outbound_gateway_ids']);

        $oldExtensionNumber = $extension->extension;
        $originalDeviceProfileId = $extension->device_profile_id;

        try {
            $extension->update($attributes);

            if ($featureAttributes !== []) {
                $extension = $this->extensionFeatureService->updateFeatures($extension, $featureAttributes);
            }
        } catch (InvalidArgumentException $exception) {
            return $this->featureValidationResponse($exception);
        }

        $this->syncOutboundPolicy($extension, $allowedOutboundDidIds, $allowedOutboundGatewayIds);
        // Reconcile the reverse link only when this extension's own
        // device_profile_id actually changed. The admin form always submits the
        // field — null for a user-owned extension — so acting on its value
        // alone would unlink a phone assigned from the Devices page on any
        // unrelated edit.
        if ($extension->device_profile_id !== $originalDeviceProfileId) {
            $this->syncOwnedDevice($extension, $extension->device_profile_id);
        }

        if ((($attributes['extension'] ?? null) !== null && $oldExtensionNumber !== $extension->extension)
            || array_key_exists('is_active', $attributes)) {
            app(\App\Services\Flow\FlowArtifactService::class)->refreshTeamRoutingArtifactsForExtension($extension);
        }

        $this->webhookDispatcher->dispatch($organization->id, 'extension.updated', [
            'extension_id' => $extension->id,
            'extension' => $extension->extension,
        ]);

        return new ExtensionResource($extension->load(['allowedOutboundDids:id', 'allowedOutboundGateways:id']));
    }

    /**
     * Delete an extension.
     */
    public function destroy(Organization $organization, Extension $extension): JsonResponse
    {
        if ($extension->organization_id !== $organization->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $this->authorize('delete', $extension);

        $extensionNumber = $extension->extension;
        $extensionId = $extension->id;
        $extension->delete();

        $this->webhookDispatcher->dispatch($organization->id, 'extension.deleted', [
            'extension_id' => $extensionId,
            'extension' => $extensionNumber,
        ]);

        return response()->json(null, 204);
    }

    /**
     * Get SIP connection configuration for an extension.
     *
     * Returns standard SIP client credentials and a WebRTC status indicator.
     * Settings are derived from the internal SIP profile.
     */
    public function sipConfig(Organization $organization, Extension $extension): JsonResponse
    {
        if ($extension->organization_id !== $organization->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $this->authorize('view', $extension);

        $config = $this->webRtcConfigService->forExtension($extension->loadMissing('organization'), config('app.url'));

        return response()->json(['data' => $config]);
    }

    protected function syncOutboundPolicy(Extension $extension, array $allowedOutboundDidIds, array $allowedOutboundGatewayIds): void
    {
        $didChanges = $extension->allowedOutboundDids()->sync($allowedOutboundDidIds);
        $gatewayChanges = $extension->allowedOutboundGateways()->sync($allowedOutboundGatewayIds);

        $policyChanged = $didChanges !== ['attached' => [], 'detached' => [], 'updated' => []]
            || $gatewayChanges !== ['attached' => [], 'detached' => [], 'updated' => []];

        if (! $policyChanged) {
            return;
        }

        $this->manifestBuilder->buildAndActivate($extension->organization);
        $extension->deviceProfiles()->where('is_active', true)->update([
            'updated_at' => now(),
        ]);
    }

    protected function extractFeatureAttributes(array &$attributes): array
    {
        $featureAttributes = [];

        foreach (['follow_me_enabled', 'follow_me_destination', 'dnd_enabled'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $featureAttributes[$key] = $attributes[$key];
                unset($attributes[$key]);
            }
        }

        return $featureAttributes;
    }

    protected function featureValidationResponse(InvalidArgumentException $exception): JsonResponse
    {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                'follow_me_destination' => [$exception->getMessage()],
            ],
        ], 422);
    }

    protected function syncOwnedDevice(Extension $extension, ?string $deviceProfileId): void
    {
        if ($deviceProfileId) {
            DeviceProfile::where('organization_id', $extension->organization_id)
                ->where('extension_id', $extension->id)
                ->where('id', '!=', $deviceProfileId)
                ->update(['extension_id' => null]);

            DeviceProfile::where('organization_id', $extension->organization_id)
                ->where('id', $deviceProfileId)
                ->update(['extension_id' => $extension->id]);

            return;
        }

        DeviceProfile::where('organization_id', $extension->organization_id)
            ->where('extension_id', $extension->id)
            ->update(['extension_id' => null]);
    }
}
