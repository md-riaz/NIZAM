<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Validates the date range a report endpoint accepts, before parsing it.
 *
 * These controllers handed request input straight to `Carbon::parse`, which
 * throws `InvalidFormatException` on a malformed string and `TypeError` on an
 * array. Both surfaced as a 500, so `?date_to=nonsense` — or the array a query
 * string can always produce with `?date_to[]=x` — crashed the endpoint instead
 * of being reported as invalid input.
 */
trait ValidatesReportRange
{
    /**
     * The validated range, defaulting to the last 30 days.
     *
     * @param  string  $fromKey  Request key for the lower bound.
     * @param  string  $toKey  Request key for the upper bound.
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function reportRange(
        Request $request,
        string $fromKey = 'date_from',
        string $toKey = 'date_to',
    ): array {
        $validated = $request->validate([
            $fromKey => ['nullable', 'date'],
            $toKey => ['nullable', 'date'],
        ]);

        $from = Carbon::parse($validated[$fromKey] ?? now()->subDays(30)->toDateString());
        $to = Carbon::parse($validated[$toKey] ?? now()->toDateString());

        // A reversed range would otherwise return nothing at all with no hint as
        // to why; swapping is what the caller plainly meant.
        return $from->greaterThan($to) ? [$to, $from] : [$from, $to];
    }
}
