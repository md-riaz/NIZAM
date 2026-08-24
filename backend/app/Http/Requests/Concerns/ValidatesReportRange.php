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

        return $this->orderedRange(
            $this->suppliedBound($validated, $fromKey),
            $this->suppliedBound($validated, $toKey),
            Carbon::today()->subDays(30),
            Carbon::today(),
        );
    }

    /**
     * A bound the caller actually supplied, or null.
     *
     * A key present but blank — `?date_to=` is what an empty form field sends —
     * is treated as absent. `Carbon::parse('')` silently returns the current
     * time, so a blank field would otherwise read as an explicit "now".
     *
     * @param  array<string, mixed>  $validated
     */
    protected function suppliedBound(array $validated, string $key): ?Carbon
    {
        $value = $validated[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * Fill in whichever bound the caller left out, then put the pair in order.
     *
     * Only a range the caller supplied in full is reordered: transposed bounds
     * are plainly a mistake, and swapping them is what was meant.
     *
     * A single bound is never swapped with the default generated opposite it,
     * which used to return the range *between* the two — the opposite of what
     * was asked for. `?date_to=2020-01-01` reported the 30 days from a month ago
     * up to 2020 rather than the 30 days ending at the requested bound. Instead
     * the default window keeps its length and slides to meet the explicit bound,
     * so a supplied bound always bounds the answer.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function orderedRange(?Carbon $from, ?Carbon $to, Carbon $defaultFrom, Carbon $defaultTo): array
    {
        if ($from && $to) {
            return $from->greaterThan($to) ? [$to, $from] : [$from, $to];
        }

        $window = (int) abs($defaultFrom->diffInDays($defaultTo));

        if ($to) {
            return [
                $to->greaterThanOrEqualTo($defaultFrom) ? $defaultFrom : $to->copy()->subDays($window),
                $to,
            ];
        }

        if ($from) {
            return [
                $from,
                $from->greaterThan($defaultTo) ? $from->copy()->addDays($window) : $defaultTo,
            ];
        }

        return [$defaultFrom, $defaultTo];
    }
}
