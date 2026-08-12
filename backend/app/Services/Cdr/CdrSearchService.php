<?php

namespace App\Services\Cdr;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Models\Recording;
use App\Support\DateRangeFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CdrSearchService
{
    /**
     * Search CDRs with advanced filters.
     */
    public function search(Organization $organization, Request $request, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->baseQuery($organization, $request);

        // callSession lets a call-history row link to the interaction journey
        // without an N+1 lookup per row.
        $query->with('callSession:id,call_uuid');

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query->paginate(min($perPage, 100));
    }

    /**
     * Aggregate counters for the *same* result set `search()` would return.
     *
     * The call-history KPI tiles used to come from the analytics summary
     * endpoint, which only understands a date range — so narrowing the table by
     * number or direction left the tiles reporting a different, wider set of
     * calls. Deriving them from the identical filter chain keeps the two in step.
     */
    public function summarize(Organization $organization, Request $request): array
    {
        $filtered = fn (): Builder => $this->applyFilters(
            CallDetailRecord::query()->where('organization_id', $organization->id),
            $request
        );

        $totalCalls = $filtered()->count();
        $answeredCalls = $filtered()->whereNotNull('answer_stamp')->count();
        $totalBillsec = (int) $filtered()->sum('billsec');

        return [
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'missed_calls' => $filtered()->whereNull('answer_stamp')
                ->whereIn('hangup_cause', ['NO_ANSWER', 'ALLOTTED_TIMEOUT', 'USER_BUSY'])
                ->count(),
            'failed_calls' => $filtered()->whereNull('answer_stamp')
                ->whereNotIn('hangup_cause', ['NORMAL_CLEARING', 'NO_ANSWER', 'ALLOTTED_TIMEOUT', 'USER_BUSY', 'ORIGINATOR_CANCEL'])
                ->count(),
            'total_duration_seconds' => (int) $filtered()->sum('duration'),
            'total_billsec_seconds' => $totalBillsec,
            // Answer Seizure Ratio: answered / attempted.
            'asr' => $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 1) : 0.0,
            // Average Call Duration: talk time across answered calls only.
            'acd_seconds' => $answeredCalls > 0 ? round($totalBillsec / $answeredCalls, 1) : 0.0,
        ];
    }

    /**
     * Organization-scoped query with the relations a list row needs.
     *
     * Recordings are only eager-loaded for users allowed to see them; otherwise
     * the CDR payload would hand recording metadata to anyone who can read call
     * history, bypassing the recordings permission.
     */
    protected function baseQuery(Organization $organization, Request $request): Builder
    {
        $query = CallDetailRecord::query()
            ->where('organization_id', $organization->id)
            ->with('enrichment');

        if ($this->canViewRecordings($request)) {
            $query->with('recordings');
        }

        return $query;
    }

    /**
     * Whether the requesting user may see recording metadata.
     */
    public function canViewRecordings(Request $request): bool
    {
        return (bool) $request->user()?->can('viewAny', Recording::class);
    }

    /**
     * Apply every supported filter to a CDR query.
     *
     * Shared by list, summary, and export so a filter added in one place cannot
     * silently go missing in another — the CSV export previously ignored the
     * `search` box entirely and exported unrelated rows.
     */
    public function applyFilters(Builder $query, Request $request): Builder
    {
        // Free-text search across caller/destination numbers and the call UUID.
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('caller_id_number', 'LIKE', "%{$search}%")
                    ->orWhere('caller_id_name', 'LIKE', "%{$search}%")
                    ->orWhere('destination_number', 'LIKE', "%{$search}%")
                    ->orWhere('uuid', 'LIKE', "%{$search}%");
            });
        }

        foreach (['direction', 'call_type', 'caller_id_number', 'destination_number', 'uuid', 'hangup_cause'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->input($column));
            }
        }

        if ($request->filled('date_from')) {
            $query->where('start_stamp', '>=', DateRangeFilter::start($request->input('date_from')));
        }

        if ($request->filled('date_to')) {
            $query->where('start_stamp', '<=', DateRangeFilter::end($request->input('date_to')));
        }

        if ($request->filled('duration_min')) {
            $query->where('duration', '>=', (int) $request->input('duration_min'));
        }

        if ($request->filled('duration_max')) {
            $query->where('duration', '<=', (int) $request->input('duration_max'));
        }

        if ($request->filled('quality_score_min')) {
            $query->where('quality_score', '>=', (int) $request->input('quality_score_min'));
        }

        if ($request->filled('mos_score_min')) {
            $query->where('mos_score', '>=', (float) $request->input('mos_score_min'));
        }

        if ($request->filled('tags')) {
            $tags = is_array($request->input('tags'))
                ? $request->input('tags')
                : explode(',', $request->input('tags'));

            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

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

        return $query;
    }

    /**
     * Apply a whitelisted sort, defaulting to newest first.
     */
    public function applySorting(Builder $query, Request $request): Builder
    {
        $allowedSorts = ['start_stamp', 'duration', 'billsec', 'quality_score', 'mos_score', 'caller_id_number', 'destination_number'];

        $sortBy = $request->input('sort_by', 'start_stamp');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        return in_array($sortBy, $allowedSorts, true)
            ? $query->orderBy($sortBy, $sortDir)
            : $query->orderBy('start_stamp', 'desc');
    }
}
