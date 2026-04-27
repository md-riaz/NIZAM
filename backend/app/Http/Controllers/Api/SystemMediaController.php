<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function storage_path;

/**
 * API controller for managing system media (audio prompts, MOH) via Spatie Media Library.
 */
class SystemMediaController extends Controller
{
    /**
     * List all media items for an organization in a given collection.
     */
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $collection = $request->query('collection', 'prompts');

        $media = $organization->media()
            ->where('collection_name', $collection)
            ->orderBy('name')
            ->get()
            ->map(fn (Media $item) => $this->serializeMedia($item));

        return response()->json(['data' => $media]);
    }

    /**
     * Upload a new media file to a organization's collection.
     */
    public function store(Request $request, Organization $organization): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:wav,mp3,ogg', 'max:20480'],
            'name' => ['sometimes', 'string', 'max:255'],
            'collection' => ['sometimes', 'string', 'in:prompts,moh'],
        ]);

        $collection = $request->input('collection', 'prompts');
        $name = $request->input('name', $request->file('file')->getClientOriginalName());

        $media = $organization
            ->addMediaFromRequest('file')
            ->usingName($name)
            ->toMediaCollection($collection);

        return response()->json([
            'data' => $this->serializeMedia($media),
        ], 201);
    }

    /**
     * Show a single media item.
     */
    public function show(Organization $organization, int $mediaId): JsonResponse
    {
        $media = $organization->media()->findOrFail($mediaId);

        return response()->json([
            'data' => $this->serializeMedia($media),
        ]);
    }

    /**
     * Update a media item's name or custom properties.
     */
    public function update(Request $request, Organization $organization, int $mediaId): JsonResponse
    {
        $media = $organization->media()->findOrFail($mediaId);

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
            'data' => $this->serializeMedia($media->fresh()),
        ]);
    }

    /**
     * Delete a media item.
     */
    public function destroy(Organization $organization, int $mediaId): JsonResponse
    {
        $media = $organization->media()->findOrFail($mediaId);
        $media->delete();

        return response()->json(null, 204);
    }

    protected function serializeMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'uuid' => $media->uuid,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'custom_properties' => $media->custom_properties,
            'collection_name' => $media->collection_name,
            'created_at' => $media->created_at,
            'url' => $media->getUrl(),
            'path' => storage_path('app/public/'.$media->id.'/'.$media->file_name),
        ];
    }
}
