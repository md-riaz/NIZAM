<?php

namespace App\Services\Routing;

use App\Models\Did;
use App\Models\Gateway;
use App\Models\Organization;
use App\Services\DidNormalizationService;

class NumberRoutingService
{
    public function resolveInboundDid(
        Organization $organization,
        string $destinationNumber,
        ?Gateway $gateway = null,
    ): ?Did {
        $normalized = DidNormalizationService::toE164($destinationNumber, $this->defaultCountryCode($organization));

        $query = Did::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->where(function ($builder) use ($destinationNumber, $normalized) {
                $builder->where('number', $destinationNumber)
                    ->orWhere('normalized_number', $normalized);
            });

        if ($gateway) {
            $gatewayMatch = (clone $query)
                ->where('gateway_id', $gateway->id)
                ->first();

            if ($gatewayMatch) {
                return $gatewayMatch;
            }
        }

        return (clone $query)
            ->whereNull('gateway_id')
            ->first();
    }

    protected function defaultCountryCode(Organization $organization): string
    {
        return (string) data_get($organization->settings, 'default_country_code', '1');
    }
}
