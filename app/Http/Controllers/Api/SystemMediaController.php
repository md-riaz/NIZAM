<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * API controller for managing system media (audio prompts, MOH) via Spatie Media Library.
 */
class SystemMediaController extends Controller
{
    /**
     * List all media items for a tenant in a given collection.
     */
    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $collection = $request->query('collection', 'prompts');

        $media = $tenant->getMedia($collection)->map(fn (Media $item) => [
            'id' => $item->id,
            'uuid' => $item->uuid,
            'name' => $item->name,
            'file_name' => $item->file_name,
            'mime_type' => $item->mime_type,
            'size' => $item->size,
            'custom_properties' => $item->custom_properties,
            'created_at' => $item->created_at,
            'url' => $item->getUrl(),
        ]);

        return response()->json(['data' => $media]);
    }

    /**
     * Upload a new media file to a tenant's collection.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:wav,mp3,ogg', 'max:20480'],
            'name' => ['sometimes', 'string', 'max:255'],
            'collection' => ['sometimes', 'string', 'in:prompts,moh'],
        ]);

        $collection = $request->input('collection', 'prompts');
        $name = $request->input('name', $request->file('file')->getClientOriginalName());

        $media = $tenant
            ->addMediaFromRequest('file')
            ->usingName($name)
            ->toMediaCollection($collection);

        return response()->json([
            'data' => [
                'id' => $media->id,
                'uuid' => $media->uuid,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getUrl(),
                'created_at' => $media->created_at,
            ],
        ], 201);
    }

    /**
     * Show a single media item.
     */
    public function show(Tenant $tenant, int $mediaId): JsonResponse
    {
        $media = $tenant->media()->findOrFail($mediaId);

        return response()->json([
            'data' => [
                'id' => $media->id,
                'uuid' => $media->uuid,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'custom_properties' => $media->custom_properties,
                'collection_name' => $media->collection_name,
                'url' => $media->getUrl(),
                'created_at' => $media->created_at,
            ],
        ]);
    }

    /**
     * Update a media item's name or custom properties.
     */
    public function update(Request $request, Tenant $tenant, int $mediaId): JsonResponse
    {
        $media = $tenant->media()->findOrFail($mediaId);

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'custom_properties' => ['sometimes', 'array'],
        ]);

        if ($request->has('name')) {
            $media->name = $request->input('name');
        }

        if ($request->has('custom_properties')) {
            $media->custom_properties = array_merge(
                $media->custom_properties,
                $request->input('custom_properties')
            );
        }

        $media->save();

        return response()->json([
            'data' => [
                'id' => $media->id,
                'uuid' => $media->uuid,
                'name' => $media->name,
                'custom_properties' => $media->custom_properties,
            ],
        ]);
    }

    /**
     * Delete a media item.
     */
    public function destroy(Tenant $tenant, int $mediaId): JsonResponse
    {
        $media = $tenant->media()->findOrFail($mediaId);
        $media->delete();

        return response()->json(null, 204);
    }
}
