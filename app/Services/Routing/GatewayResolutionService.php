<?php

namespace App\Services\Routing;

use App\Models\Gateway;
use App\Models\Tenant;

class GatewayResolutionService
{
    public function resolveFromXmlCurl(Tenant $tenant, array $payload): array
    {
        $identifier = $payload['variable_sip_gateway_name']
            ?? $payload['sip_gateway_name']
            ?? null;

        $realm = $payload['variable_sip_req_host']
            ?? $payload['domain']
            ?? null;

        $gateway = null;

        if ($identifier) {
            $gateway = Gateway::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', $identifier)
                ->first();
        }

        if (! $gateway && $realm) {
            $gateway = Gateway::query()
                ->where('tenant_id', $tenant->id)
                ->where(function ($query) use ($realm) {
                    $query->where('host', $realm)
                        ->orWhere('realm', $realm)
                        ->orWhere('proxy', $realm)
                        ->orWhere('from_domain', $realm);
                })
                ->first();
        }

        return [
            'gateway' => $gateway,
            'resolved_identifier' => $identifier,
            'resolved_realm' => $realm,
        ];
    }
}
