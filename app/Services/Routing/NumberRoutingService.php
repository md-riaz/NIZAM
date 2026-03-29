<?php

namespace App\Services\Routing;

use App\Models\Did;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Services\DidNormalizationService;

class NumberRoutingService
{
    public function resolveInboundDid(
        Tenant $tenant,
        string $destinationNumber,
        ?Gateway $gateway = null,
    ): ?Did {
        $normalized = DidNormalizationService::toE164($destinationNumber, $this->defaultCountryCode($tenant));

        $query = Did::query()
            ->where('tenant_id', $tenant->id)
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

    protected function defaultCountryCode(Tenant $tenant): string
    {
        return (string) data_get($tenant->settings, 'default_country_code', '1');
    }
}
