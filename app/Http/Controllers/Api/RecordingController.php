<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecordingResource;
use App\Models\Recording;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

/**
 * API controller for recording indexing and download.
 */
class RecordingController extends Controller
{
    public function index(Request $request, Tenant $tenant): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Recording::class);
        $query = Recording::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('call_uuid')) {
            $query->where('call_uuid', $request->input('call_uuid'));
        }

        if ($request->filled('caller_id_number')) {
            $query->where('caller_id_number', $request->input('caller_id_number'));
        }

        if ($request->filled('destination_number')) {
            $query->where('destination_number', $request->input('destination_number'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        return RecordingResource::collection($query->paginate(15));
    }

    public function show(Tenant $tenant, Recording $recording): RecordingResource|JsonResponse
    {
        $this->authorize('view', $recording);
        if ($recording->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Recording not found.'], 404);
        }

        return new RecordingResource($recording);
    }

    /**
     * Download a recording file.
     */
    public function download(Tenant $tenant, Recording $recording)
    {
        $this->authorize('download', $recording);
        if ($recording->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Recording not found.'], 404);
        }

        if (! Storage::disk('recordings')->exists($recording->file_path)) {
            return response()->json(['message' => 'Recording file not found on disk.'], 404);
        }

        return Storage::disk('recordings')->download(
            $recording->file_path,
            $recording->file_name
        );
    }

    public function destroy(Tenant $tenant, Recording $recording): JsonResponse
    {
        $this->authorize('delete', $recording);
        if ($recording->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Recording not found.'], 404);
        }

        Storage::disk('recordings')->delete($recording->file_path);
        $recording->delete();

        return response()->json(null, 204);
    }
}
