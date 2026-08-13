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

        return $query->paginate(max(1, min($perPage, 100)));
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
        // Every counter comes from one pass with conditional aggregates. The
        // summary rides along on each paginated list request, so issuing a
        // separate aggregate query per tile would multiply the cost of every
        // page view over what is typically the largest table in the schema.
        $missed = ['NO_ANSWER', 'ALLOTTED_TIMEOUT', 'USER_BUSY'];
        $notFailed = [...$missed, 'NORMAL_CLEARING', 'ORIGINATOR_CANCEL'];

        $missedList = $this->quotedList($missed);
        $notFailedList = $this->quotedList($notFailed);

        $query = $this->applyFilters(
            CallDetailRecord::query()->where('organization_id', $organization->id),
            $request
        );

        $row = $query->selectRaw(implode(', ', [
            'COUNT(*) as total_calls',
            'SUM(CASE WHEN answer_stamp IS NOT NULL THEN 1 ELSE 0 END) as answered_calls',
            "SUM(CASE WHEN answer_stamp IS NULL AND hangup_cause IN ({$missedList}) THEN 1 ELSE 0 END) as missed_calls",
            "SUM(CASE WHEN answer_stamp IS NULL AND (hangup_cause IS NULL OR hangup_cause NOT IN ({$notFailedList})) THEN 1 ELSE 0 END) as failed_calls",
            'COALESCE(SUM(duration), 0) as total_duration',
            'COALESCE(SUM(billsec), 0) as total_billsec',
        ]))->first();

        $totalCalls = (int) ($row->total_calls ?? 0);
        $answeredCalls = (int) ($row->answered_calls ?? 0);
        $totalBillsec = (int) ($row->total_billsec ?? 0);

        return [
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'missed_calls' => (int) ($row->missed_calls ?? 0),
            'failed_calls' => (int) ($row->failed_calls ?? 0),
            'total_duration_seconds' => (int) ($row->total_duration ?? 0),
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
        if (($search = $this->scalar($request, 'search')) !== null) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('caller_id_number', 'LIKE', "%{$search}%")
                    ->orWhere('caller_id_name', 'LIKE', "%{$search}%")
                    ->orWhere('destination_number', 'LIKE', "%{$search}%")
                    ->orWhere('uuid', 'LIKE', "%{$search}%");
            });
        }

        foreach (['direction', 'call_type', 'caller_id_number', 'destination_number', 'uuid', 'hangup_cause'] as $column) {
            if (($value = $this->scalar($request, $column)) !== null) {
                $query->where($column, $value);
            }
        }

        if (($from = $this->scalar($request, 'date_from')) !== null) {
            $query->where('start_stamp', '>=', DateRangeFilter::start($from));
        }

        if (($to = $this->scalar($request, 'date_to')) !== null) {
            $query->where('start_stamp', '<=', DateRangeFilter::end($to));
        }

        if (($value = $this->scalar($request, 'duration_min')) !== null) {
            $query->where('duration', '>=', (int) $value);
        }

        if (($value = $this->scalar($request, 'duration_max')) !== null) {
            $query->where('duration', '<=', (int) $value);
        }

        if (($value = $this->scalar($request, 'quality_score_min')) !== null) {
            $query->where('quality_score', '>=', (int) $value);
        }

        if (($value = $this->scalar($request, 'mos_score_min')) !== null) {
            $query->where('mos_score', '>=', (float) $value);
        }

        if ($request->filled('tags')) {
            $tags = is_array($request->input('tags'))
                ? $request->input('tags')
                : explode(',', $request->input('tags'));

            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        foreach (['destination_country', 'number_type'] as $column) {
            if (($value = $this->scalar($request, $column)) !== null) {
                $query->whereHas('enrichment', fn ($q) => $q->where($column, $value));
            }
        }

        return $query;
    }

    /**
     * A filter value as a string, or null when it is absent or not scalar.
     *
     * Query strings can carry arrays (`?date_to[]=x`). Handing one to a date
     * parser or a query binding raised a TypeError and answered 500, so a
     * non-scalar value is treated as no filter at all.
     */
    protected function scalar(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    /**
     * Render a fixed list of hangup causes for inlining in a CASE expression.
     *
     * The values are class constants rather than user input, but they are quoted
     * through the connection anyway so this cannot become an injection point if
     * the list ever grows from somewhere less trusted.
     *
     * @param  array<int, string>  $values
     */
    protected function quotedList(array $values): string
    {
        $pdo = CallDetailRecord::query()->getConnection()->getPdo();

        return implode(', ', array_map(
            static fn (string $value): string => $pdo->quote($value),
            $values
        ));
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
