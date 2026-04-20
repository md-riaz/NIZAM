<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NumberLookupService
{
    /**
     * Look up caller information from an external source.
     *
     * Organizations configure a lookup URL in their settings under 'number_lookup_url'.
     * The service sends a GET request with the number as a query parameter
     * and returns the response data (e.g., caller name for CNAM).
     */
    public function lookup(Organization $organization, string $number): ?array
    {
        $url = $organization->settings['number_lookup_url'] ?? null;

        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-Organization-Id' => $organization->id,
                    'X-Organization-Domain' => $organization->domain,
                ])
                ->get($url, ['number' => $number]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Number lookup failed', [
                'organization_id' => $organization->id,
                'number' => $number,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('Number lookup error', [
                'organization_id' => $organization->id,
                'number' => $number,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
