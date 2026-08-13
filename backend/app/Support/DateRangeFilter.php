<?php

namespace App\Support;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * Widens bare `YYYY-MM-DD` filter values to cover the whole day.
 *
 * Date pickers submit a bare date, and comparing `<= 2026-08-12` against a
 * timestamp column excludes every row on the 12th — so a single-day range that
 * clearly contains calls came back empty. Values that already carry a time are
 * passed through untouched.
 */
final class DateRangeFilter
{
    /**
     * Lower bound: midnight when only a date was given.
     */
    public static function start(string $value): Carbon|string
    {
        return self::bound($value, endOfDay: false);
    }

    /**
     * Upper bound: the last instant of the day when only a date was given.
     */
    public static function end(string $value): Carbon|string
    {
        return self::bound($value, endOfDay: true);
    }

    private static function bound(string $value, bool $endOfDay): Carbon|string
    {
        $trimmed = trim($value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return $value;
        }

        try {
            $parsed = Carbon::parse($trimmed);
        } catch (InvalidFormatException) {
            // Unparseable input degrades to a literal comparison rather than a 500.
            return $value;
        }

        return $endOfDay ? $parsed->endOfDay() : $parsed->startOfDay();
    }
}
