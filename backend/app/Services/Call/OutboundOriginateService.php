<?php

namespace App\Services\Call;

use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Organization;
use App\Services\PhoneNumberAccessResolver;
use InvalidArgumentException;

class OutboundOriginateService
{
    public function __construct(
        protected PhoneNumberAccessResolver $phoneNumberAccessResolver,
    ) {}

    public function buildCommand(
        Organization $organization,
        Extension $extension,
        string $destination,
        ?string $callerIdName = null,
        ?string $didId = null,
        ?string $gatewayId = null,
    ): string {
        $resolved = $this->phoneNumberAccessResolver->resolve($extension, $didId, $gatewayId);
        $did = $resolved['did'];
        $gateway = $resolved['gateway'];

        if ($did === null) {
            throw new InvalidArgumentException('This extension does not have an allowed outbound DID.');
        }

        $callerIdName ??= $extension->effective_caller_id_name ?? $extension->first_name;
        $callerIdNumber = $did->normalized_number ?? $did->number;

        $endpoint = $gateway instanceof Gateway
            ? sprintf('&bridge(sofia/gateway/v_%s/%s)', $gateway->id, $destination)
            : sprintf('%s XML %s', $destination, $organization->domain);

        return sprintf(
            'originate {origination_caller_id_name=%s,origination_caller_id_number=%s}user/%s@%s %s',
            $callerIdName,
            $callerIdNumber,
            $extension->extension,
            $organization->domain,
            $endpoint,
        );
    }
}
