<?php

namespace App\Services;

/**
 * Normalizes inbound DID numbers to E.164 format.
 *
 * Carriers deliver numbers in many formats (+, 00, national, local).
 * This service normalizes before routing to avoid "works with one carrier only" issues.
 */
class DidNormalizationService
{
    /**
     * Normalize a phone number to E.164 format.
     *
     * Handles common carrier formats:
     *   +15551234567  → +15551234567  (already E.164)
     *   15551234567   → +15551234567  (missing +)
     *   0015551234567 → +15551234567  (international prefix 00)
     *   011155512345  → +1155512345   (US international prefix 011)
     *   5551234567    → +15551234567  (national, with default country)
     *
     * @param  string  $number  Raw inbound number from carrier
     * @param  string  $defaultCountryCode  Default country code (without +) when number appears national
     * @return string E.164 formatted number (with leading +)
     */
    public static function toE164(string $number, string $defaultCountryCode = '1'): string
    {
        // Strip whitespace, dashes, parentheses, and dots
        $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', $number);

        // Already E.164
        if (preg_match('/^\+[1-9]\d{6,14}$/', $cleaned)) {
            return $cleaned;
        }

        // Remove leading + if present but doesn't match E.164 (edge case)
        $cleaned = ltrim($cleaned, '+');

        // Remove international dial prefix "00"
        if (str_starts_with($cleaned, '00')) {
            $cleaned = substr($cleaned, 2);
        }

        // Remove US international dial prefix "011"
        if (str_starts_with($cleaned, '011')) {
            $cleaned = substr($cleaned, 3);
        }

        // If the number looks like it already has a country code (starts with
        // a valid country code and is long enough), prepend +
        if (preg_match('/^[1-9]\d{6,14}$/', $cleaned)) {
            // Heuristic: if length > 10, assume country code is included
            if (strlen($cleaned) > 10) {
                return '+'.$cleaned;
            }

            // Otherwise, prepend default country code
            return '+'.$defaultCountryCode.$cleaned;
        }

        // Fallback: return with + prefix and default country code
        if (strlen($cleaned) > 0) {
            return '+'.$defaultCountryCode.$cleaned;
        }

        return '+'.$cleaned;
    }

    /**
     * Strip a number to digits only (no + prefix).
     */
    public static function toDigitsOnly(string $number): string
    {
        return preg_replace('/[^\d]/', '', $number);
    }

    /**
     * Check if a number matches E.164 format.
     */
    public static function isE164(string $number): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $number);
    }
}
