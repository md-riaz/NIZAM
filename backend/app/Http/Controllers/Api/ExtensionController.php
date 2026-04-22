<?php

namespace App\Http\Controllers\Api;

use App\Data\ExtensionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExtensionRequest;
use App\Http\Requests\UpdateExtensionRequest;
use App\Http\Resources\ExtensionResource;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\WebRtcConfigService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing extensions scoped to a organization.
 */
class ExtensionController extends Controller
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher,
        protected WebRtcConfigService $webRtcConfigService,
    ) {}

    /**
     * List extensions for an organization (paginated).
     */
    public function index(Organization $organization)
    {
        $this->authorize('viewAny', Extension::class);

        return ExtensionResource::collection($organization->extensions()->orderBy('extension')->paginate(15));
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

        $extension = $organization->extensions()->create(ExtensionData::fromArray($request->validated())->attributes);

        $this->webhookDispatcher->dispatch($organization->id, 'extension.created', [
            'extension_id' => $extension->id,
            'extension' => $extension->extension,
        ]);

        return (new ExtensionResource($extension))->response()->setStatusCode(201);
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

        return new ExtensionResource($extension);
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

        $extension->update(ExtensionData::fromArray($request->validated())->attributes);

        $this->webhookDispatcher->dispatch($organization->id, 'extension.updated', [
            'extension_id' => $extension->id,
            'extension' => $extension->extension,
        ]);

        return new ExtensionResource($extension);
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
}
