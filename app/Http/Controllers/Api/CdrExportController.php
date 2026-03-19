<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CallDetailRecordResource;
use App\Models\CallDetailRecord;
use App\Models\Tenant;
use App\Services\Cdr\CdrSearchService;
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
     * POST /api/tenants/{tenant}/cdrs/export
     *
     * Supports format query param: csv (default), json
     */
    public function export(Request $request, Tenant $tenant): StreamedResponse|JsonResponse
    {
        $this->authorize('viewAny', CallDetailRecord::class);

        $format = $request->input('format', 'csv');

        if ($format === 'json') {
            return $this->exportJson($request, $tenant);
        }

        return $this->exportCsv($request, $tenant);
    }

    /**
     * Export CDRs as a streamed CSV download.
     */
    protected function exportCsv(Request $request, Tenant $tenant): StreamedResponse
    {
        $query = $this->buildExportQuery($request, $tenant);

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cdrs_export_' . now()->format('Y-m-d_His') . '.csv"',
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
    protected function exportJson(Request $request, Tenant $tenant): JsonResponse
    {
        $query = $this->buildExportQuery($request, $tenant);

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
     */
    protected function buildExportQuery(Request $request, Tenant $tenant)
    {
        $query = CallDetailRecord::where('tenant_id', $tenant->id)
            ->with('enrichment')
            ->orderBy('start_stamp', 'desc');

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        if ($request->filled('call_type')) {
            $query->where('call_type', $request->input('call_type'));
        }

        if ($request->filled('uuid')) {
            $query->where('uuid', $request->input('uuid'));
        }

        if ($request->filled('hangup_cause')) {
            $query->where('hangup_cause', $request->input('hangup_cause'));
        }

        if ($request->filled('caller_id_number')) {
            $query->where('caller_id_number', $request->input('caller_id_number'));
        }

        if ($request->filled('destination_number')) {
            $query->where('destination_number', $request->input('destination_number'));
        }

        if ($request->filled('date_from')) {
            $query->where('start_stamp', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('start_stamp', '<=', $request->input('date_to'));
        }

        if ($request->filled('quality_score_min')) {
            $query->where('quality_score', '>=', (int) $request->input('quality_score_min'));
        }

        if ($request->filled('mos_score_min')) {
            $query->where('mos_score', '>=', (float) $request->input('mos_score_min'));
        }

        return $query;
    }
}
