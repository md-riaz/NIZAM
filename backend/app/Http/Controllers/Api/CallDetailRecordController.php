<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CallDetailRecordResource;
use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Services\Cdr\CdrSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for viewing call detail records scoped to a organization.
 *
 * CDRs are read-only; they are created by the system.
 */
class CallDetailRecordController extends Controller
{
    public function __construct(
        protected CdrSearchService $searchService
    ) {}

    /**
     * List CDRs for an organization (paginated, with advanced filters).
     *
     * Supports query filters: search, direction, call_type, caller_id_number,
     * destination_number, date_from, date_to, duration_min, duration_max,
     * quality_score_min, mos_score_min, tags, destination_country, number_type,
     * sort_by, sort_dir.
     */
    public function index(Request $request, Organization $organization)
    {
        $this->authorize('viewAny', CallDetailRecord::class);

        $perPage = (int) $request->input('per_page', 25);

        return CallDetailRecordResource::collection(
            $this->searchService->search($organization, $request, $perPage)
        );
    }

    /**
     * Show a single CDR with enrichment data.
     */
    public function show(Organization $organization, CallDetailRecord $cdr): JsonResponse|CallDetailRecordResource
    {
        $this->authorize('view', $cdr);
        if ($cdr->organization_id !== $organization->id) {
            return response()->json(['message' => 'CDR not found.'], 404);
        }

        $cdr->load('enrichment', 'recordings');

        return new CallDetailRecordResource($cdr);
    }
}
