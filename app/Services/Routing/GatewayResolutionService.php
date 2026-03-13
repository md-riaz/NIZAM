<?php

namespace App\Services\Routing;

use App\Models\Gateway;
use App\Models\GatewayRegistration;
use App\Models\Tenant;

class GatewayResolutionService
{
    public function resolveFromXmlCurl(Tenant $tenant, array $payload): array
    {
        $identifier = $payload['variable_sofia_profile_name']
            ?? $payload['variable_sip_gateway_name']
            ?? $payload['sip_gateway_name']
            ?? null;

        $realm = $payload['variable_sip_req_host']
            ?? $payload['domain']
            ?? null;

        $registration = null;
        $gateway = null;

        if ($identifier) {
            $registration = GatewayRegistration::query()
                ->where('registration_identifier', $identifier)
                ->whereHas('gateway', fn ($query) => $query->where('tenant_id', $tenant->id))
                ->first();

            $gateway = $registration?->gateway;
        }

        if (! $gateway && $identifier) {
            $gateway = Gateway::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', $identifier)
                ->first();
        }

        if (! $gateway && $realm) {
            $gateway = Gateway::query()
                ->where('tenant_id', $tenant->id)
                ->where('host', $realm)
                ->first();
        }

        return [
            'gateway' => $gateway,
            'gateway_registration' => $registration,
            'resolved_identifier' => $identifier,
            'resolved_realm' => $realm,
        ];
    }
}
