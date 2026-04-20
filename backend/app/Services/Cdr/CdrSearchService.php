<?php

namespace App\Services\Cdr;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CdrSearchService
{
    /**
     * Search CDRs with advanced filters.
     */
    public function search(Organization $organization, Request $request, int $perPage = 25): LengthAwarePaginator
    {
        $query = CallDetailRecord::where('organization_id', $organization->id)
            ->with('enrichment');

        // Full-text search across caller/destination numbers
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('caller_id_number', 'LIKE', "%{$search}%")
                    ->orWhere('caller_id_name', 'LIKE', "%{$search}%")
                    ->orWhere('destination_number', 'LIKE', "%{$search}%")
                    ->orWhere('uuid', 'LIKE', "%{$search}%");
            });
        }

        // Direction filter
        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        // Call type filter
        if ($request->filled('call_type')) {
            $query->where('call_type', $request->input('call_type'));
        }

        // Specific number filters
        if ($request->filled('caller_id_number')) {
            $query->where('caller_id_number', $request->input('caller_id_number'));
        }

        if ($request->filled('destination_number')) {
            $query->where('destination_number', $request->input('destination_number'));
        }

        // UUID filter
        if ($request->filled('uuid')) {
            $query->where('uuid', $request->input('uuid'));
        }

        // Hangup cause filter
        if ($request->filled('hangup_cause')) {
            $query->where('hangup_cause', $request->input('hangup_cause'));
        }

        // Date range filters
        if ($request->filled('date_from')) {
            $query->where('start_stamp', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('start_stamp', '<=', $request->input('date_to'));
        }

        // Duration range filters
        if ($request->filled('duration_min')) {
            $query->where('duration', '>=', (int) $request->input('duration_min'));
        }

        if ($request->filled('duration_max')) {
            $query->where('duration', '<=', (int) $request->input('duration_max'));
        }

        // Quality filters
        if ($request->filled('quality_score_min')) {
            $query->where('quality_score', '>=', (int) $request->input('quality_score_min'));
        }

        if ($request->filled('mos_score_min')) {
            $query->where('mos_score', '>=', (float) $request->input('mos_score_min'));
        }

        // Tags filter (JSON contains)
        if ($request->filled('tags')) {
            $tags = is_array($request->input('tags'))
                ? $request->input('tags')
                : explode(',', $request->input('tags'));

            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        // Enrichment filters
        if ($request->filled('destination_country')) {
            $query->whereHas('enrichment', function ($q) use ($request) {
                $q->where('destination_country', $request->input('destination_country'));
            });
        }

        if ($request->filled('number_type')) {
            $query->whereHas('enrichment', function ($q) use ($request) {
                $q->where('number_type', $request->input('number_type'));
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'start_stamp');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['start_stamp', 'duration', 'billsec', 'quality_score', 'mos_score', 'caller_id_number', 'destination_number'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('start_stamp', 'desc');
        }

        return $query->paginate(min($perPage, 100));
    }
}
