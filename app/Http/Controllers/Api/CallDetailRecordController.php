<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CallDetailRecordResource;
use App\Models\CallDetailRecord;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API controller for viewing call detail records scoped to a tenant.
 *
 * CDRs are read-only; they are created by the system.
 */
class CallDetailRecordController extends Controller
{
    /**
     * List CDRs for a tenant (paginated, ordered by start_stamp desc).
     *
     * Supports query filters: direction, caller_id_number, destination_number, date_from, date_to.
     */
    public function index(Request $request, Tenant $tenant)
    {
        $this->authorize('viewAny', CallDetailRecord::class);
        $query = $tenant->cdrs()->orderBy('start_stamp', 'desc');

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
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

        return CallDetailRecordResource::collection($query->paginate(15));
    }

    /**
     * Show a single CDR.
     */
    public function show(Tenant $tenant, CallDetailRecord $cdr): JsonResponse|CallDetailRecordResource
    {
        $this->authorize('view', $cdr);
        if ($cdr->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'CDR not found.'], 404);
        }

        return new CallDetailRecordResource($cdr);
    }

    /**
     * Export CDRs as a streamed CSV download.
     */
    public function export(Request $request, Tenant $tenant): StreamedResponse
    {
        $this->authorize('viewAny', CallDetailRecord::class);

        $query = $tenant->cdrs()->orderBy('start_stamp', 'desc');

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
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

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cdrs.csv"',
        ];

        $columns = [
            'uuid', 'caller_id_name', 'caller_id_number', 'destination_number',
            'direction', 'start_stamp', 'answer_stamp', 'end_stamp',
            'duration', 'billsec', 'hangup_cause',
        ];

        return response()->stream(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $query->limit(10000)->cursor()->each(function ($cdr) use ($handle, $columns) {
                $row = [];
                foreach ($columns as $col) {
                    $row[] = $cdr->{$col};
                }
                fputcsv($handle, $row);
            });

            fclose($handle);
        }, 200, $headers);
    }
}
