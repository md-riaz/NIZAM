<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CallDetailRecordResource;
use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Services\Cdr\CdrSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CdrExportController extends Controller
{
    public function __construct(
        protected CdrSearchService $searchService
    ) {}

    /**
     * Export CDRs with advanced filters.
     *
     * POST /api/organizations/{organization}/cdrs/export
     *
     * Supports format query param: csv (default), json
     */
    public function export(Request $request, Organization $organization): StreamedResponse|JsonResponse
    {
        $this->authorize('viewAny', CallDetailRecord::class);

        $format = $request->input('format', 'csv');

        if ($format === 'json') {
            return $this->exportJson($request, $organization);
        }

        return $this->exportCsv($request, $organization);
    }

    /**
     * Export CDRs as a streamed CSV download.
     */
    protected function exportCsv(Request $request, Organization $organization): StreamedResponse
    {
        $query = $this->buildExportQuery($request, $organization);

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cdrs_export_'.now()->format('Y-m-d_His').'.csv"',
        ];

        $columns = [
            'uuid', 'caller_id_name', 'caller_id_number', 'destination_number',
            'direction', 'call_type', 'start_stamp', 'answer_stamp', 'end_stamp',
            'duration', 'billsec', 'hangup_cause',
            'quality_score', 'mos_score', 'packet_loss', 'jitter', 'latency',
            'sip_user_agent', 'remote_media_ip',
        ];

        return response()->stream(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $query->limit(50000)->cursor()->each(function ($cdr) use ($handle, $columns) {
                $row = [];
                foreach ($columns as $col) {
                    $row[] = $cdr->{$col};
                }
                fputcsv($handle, $row);
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export CDRs as JSON.
     */
    protected function exportJson(Request $request, Organization $organization): JsonResponse
    {
        $query = $this->buildExportQuery($request, $organization);

        $cdrs = $query->limit(10000)->get();

        return response()->json([
            'data' => CallDetailRecordResource::collection($cdrs),
            'meta' => [
                'total' => $cdrs->count(),
                'exported_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Build the export query with filters applied.
     *
     * Filters come from CdrSearchService so an export always covers exactly the
     * rows the on-screen table shows. This class used to reimplement a subset of
     * them and quietly dropped the `search` box, producing a CSV of unrelated
     * calls whenever an operator exported a narrowed view.
     */
    protected function buildExportQuery(Request $request, Organization $organization): Builder
    {
        $query = CallDetailRecord::query()
            ->where('organization_id', $organization->id)
            ->with('enrichment');

        $this->searchService->applyFilters($query, $request);
        $this->searchService->applySorting($query, $request);

        return $query;
    }
}
