<?php

namespace App\Services\Routing;

use App\Models\Did;
use App\Models\Gateway;
use App\Models\GatewayRegistration;
use App\Models\Tenant;
use App\Services\DidNormalizationService;

class NumberRoutingService
{
    public function resolveInboundDid(
        Tenant $tenant,
        string $destinationNumber,
        ?Gateway $gateway = null,
        ?GatewayRegistration $gatewayRegistration = null
    ): ?Did {
        $normalized = DidNormalizationService::toE164($destinationNumber, $this->defaultCountryCode($tenant));

        $query = Did::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where(function ($builder) use ($destinationNumber, $normalized) {
                $builder->where('number', $destinationNumber)
                    ->orWhere('normalized_number', $normalized);
            });

        if ($gatewayRegistration) {
            $registrationMatch = (clone $query)
                ->where('gateway_registration_id', $gatewayRegistration->id)
                ->first();

            if ($registrationMatch) {
                return $registrationMatch;
            }
        }

        if ($gateway) {
            $gatewayMatch = (clone $query)
                ->where('gateway_id', $gateway->id)
                ->first();

            if ($gatewayMatch) {
                return $gatewayMatch;
            }
        }

        return $query->first();
    }

    protected function defaultCountryCode(Tenant $tenant): string
    {
        return (string) data_get($tenant->settings, 'default_country_code', '1');
    }
}
