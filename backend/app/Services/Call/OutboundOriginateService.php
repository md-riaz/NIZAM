<?php

namespace App\Services\Call;

use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Organization;
use App\Services\PhoneNumberAccessResolver;

class OutboundOriginateService
{
    public function __construct(
        protected ?PhoneNumberAccessResolver $phoneNumberAccessResolver = null,
    ) {}

    public function buildCommand(
        Organization $organization,
        Extension $extension,
        string $destination,
        ?string $callerIdName = null,
        ?string $callerIdNumber = null,
        ?Gateway $gateway = null,
    ): string {
        $resolvedCallerId = ($this->phoneNumberAccessResolver ?? new PhoneNumberAccessResolver)->resolveForExtension($extension);

        $callerIdName ??= $resolvedCallerId['name'];
        $callerIdNumber ??= $resolvedCallerId['number'];

        $endpoint = $gateway
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
