<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NumberLookupService
{
    /**
     * Look up caller information from an external source.
     *
     * Tenants configure a lookup URL in their settings under 'number_lookup_url'.
     * The service sends a GET request with the number as a query parameter
     * and returns the response data (e.g., caller name for CNAM).
     */
    public function lookup(Tenant $tenant, string $number): ?array
    {
        $url = $tenant->settings['number_lookup_url'] ?? null;

        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-Tenant-Id' => $tenant->id,
                    'X-Tenant-Domain' => $tenant->domain,
                ])
                ->get($url, ['number' => $number]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Number lookup failed', [
                'tenant_id' => $tenant->id,
                'number' => $number,
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::error('Number lookup error', [
                'tenant_id' => $tenant->id,
                'number' => $number,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
