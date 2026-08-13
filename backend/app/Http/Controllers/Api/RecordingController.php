<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecordingResource;
use App\Models\Organization;
use App\Models\Recording;
use App\Services\Storage\StorageDriver;
use App\Support\DateRangeFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

/**
 * API controller for recording indexing and download.
 */
class RecordingController extends Controller
{
    public function __construct(
        protected ?StorageDriver $storageDriver = null,
    ) {}

    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Recording::class);
        $query = Recording::where('organization_id', $organization->id)
            ->orderBy('created_at', 'desc');

        if (($value = $this->scalarFilter($request, 'call_uuid')) !== null) {
            $query->where('call_uuid', $value);
        }

        // Numbers match on a substring: an operator searching for a recording
        // types the digits they remember, not the exact stored E.164 string.
        foreach (['caller_id_number', 'destination_number'] as $column) {
            if (($value = $this->scalarFilter($request, $column)) !== null) {
                $query->where($column, 'LIKE', '%'.$value.'%');
            }
        }

        if (($from = $this->scalarFilter($request, 'date_from')) !== null) {
            $query->where('created_at', '>=', DateRangeFilter::start($from));
        }

        if (($to = $this->scalarFilter($request, 'date_to')) !== null) {
            $query->where('created_at', '<=', DateRangeFilter::end($to));
        }

        $perPage = max(1, min((int) $request->input('per_page', 15) ?: 15, 100));

        return RecordingResource::collection($query->paginate($perPage));
    }

    /**
     * A filter value as a string, or null when it is absent or not scalar.
     *
     * A query string can carry an array (`?date_to[]=x`); passing one to a date
     * parser or a query binding raised a TypeError and answered 500.
     */
    protected function scalarFilter(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    public function show(Organization $organization, Recording $recording): RecordingResource|JsonResponse
    {
        $this->authorize('view', $recording);
        if ($recording->organization_id !== $organization->id) {
            return response()->json(['message' => 'Recording not found.'], 404);
        }

        return new RecordingResource($recording);
    }

    /**
     * Download a recording file.
     */
    public function download(Organization $organization, Recording $recording)
    {
        $this->authorize('download', $recording);
        if ($recording->organization_id !== $organization->id) {
            return response()->json(['message' => 'Recording not found.'], 404);
        }

        if ($recording->storage_driver === 'local') {
            if (! $this->storageDriver()->exists($recording->file_path)) {
                return response()->json(['message' => 'Recording file not found on disk.'], 404);
            }
        } elseif (! Storage::disk('recordings')->exists($recording->file_path)) {
            return response()->json(['message' => 'Recording file not found on disk.'], 404);
        }

        return Storage::disk('recordings')->download(
            $recording->file_path,
            $recording->file_name
        );
    }

    public function destroy(Organization $organization, Recording $recording): JsonResponse
    {
        $this->authorize('delete', $recording);
        if ($recording->organization_id !== $organization->id) {
            return response()->json(['message' => 'Recording not found.'], 404);
        }

        if ($recording->storage_driver === 'local') {
            $this->storageDriver()->delete($recording->file_path);
        } else {
            Storage::disk('recordings')->delete($recording->file_path);
        }

        $recording->delete();

        return response()->json(null, 204);
    }

    protected function storageDriver(): StorageDriver
    {
        $this->storageDriver ??= app(StorageDriver::class);

        return $this->storageDriver;
    }
}
