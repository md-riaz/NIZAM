<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExtensionRequest;
use App\Http\Requests\UpdateExtensionRequest;
use App\Http\Resources\ExtensionResource;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;

/**
 * API controller for managing extensions scoped to a tenant.
 */
class ExtensionController extends Controller
{
    public function __construct(
        protected WebhookDispatcher $webhookDispatcher
    ) {}

    /**
     * List extensions for a tenant (paginated).
     */
    public function index(Tenant $tenant)
    {
        $this->authorize('viewAny', Extension::class);

        return ExtensionResource::collection($tenant->extensions()->paginate(15));
    }

    /**
     * Create a new extension for a tenant.
     */
    public function store(StoreExtensionRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', Extension::class);

        if ($tenant->max_extensions > 0 && $tenant->extensions()->count() >= $tenant->max_extensions) {
            return response()->json([
                'message' => 'Extension quota exceeded. Maximum allowed: '.$tenant->max_extensions,
            ], 422);
        }

        $extension = $tenant->extensions()->create($request->validated());

        $this->webhookDispatcher->dispatch($tenant->id, 'extension.created', [
            'extension_id' => $extension->id,
            'extension' => $extension->extension,
        ]);

        return (new ExtensionResource($extension))->response()->setStatusCode(201);
    }

    /**
     * Show a single extension.
     */
    public function show(Tenant $tenant, Extension $extension): JsonResponse|ExtensionResource
    {
        if ($extension->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $this->authorize('view', $extension);

        return new ExtensionResource($extension);
    }

    /**
     * Update an existing extension.
     */
    public function update(UpdateExtensionRequest $request, Tenant $tenant, Extension $extension): JsonResponse|ExtensionResource
    {
        if ($extension->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $this->authorize('update', $extension);

        $extension->update($request->validated());

        $this->webhookDispatcher->dispatch($tenant->id, 'extension.updated', [
            'extension_id' => $extension->id,
            'extension' => $extension->extension,
        ]);

        return new ExtensionResource($extension);
    }

    /**
     * Delete an extension.
     */
    public function destroy(Tenant $tenant, Extension $extension): JsonResponse
    {
        if ($extension->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $this->authorize('delete', $extension);

        $extensionNumber = $extension->extension;
        $extensionId = $extension->id;
        $extension->delete();

        $this->webhookDispatcher->dispatch($tenant->id, 'extension.deleted', [
            'extension_id' => $extensionId,
            'extension' => $extensionNumber,
        ]);

        return response()->json(null, 204);
    }
}
