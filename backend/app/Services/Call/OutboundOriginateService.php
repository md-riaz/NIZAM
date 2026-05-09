<?php

namespace App\Services\Call;

use App\Models\CallSession;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Organization;
use App\Services\PhoneNumberAccessResolver;
use Illuminate\Support\Str;
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
        $originationUuid = (string) Str::uuid();

        $this->stageOutboundSession($organization, $extension, $did, $originationUuid);

        $endpoint = $gateway instanceof Gateway
            ? sprintf('&bridge(sofia/gateway/v_%s/%s)', $gateway->id, $destination)
            : sprintf('%s XML %s', $destination, $organization->domain);

        return sprintf(
            'originate {origination_uuid=%s,origination_caller_id_name=%s,origination_caller_id_number=%s}user/%s@%s %s',
            $originationUuid,
            $callerIdName,
            $callerIdNumber,
            $extension->extension,
            $organization->domain,
            $endpoint,
        );
    }

    protected function stageOutboundSession(Organization $organization, Extension $extension, Did $did, string $callUuid): void
    {
        $session = CallSession::firstOrNew(['call_uuid' => $callUuid]);

        $session->fill([
            'organization_id' => $organization->id,
            'did_id' => $did->id,
            'state' => $session->state ?: 'initiated',
            'variables' => array_merge($session->variables ?? [], [
                'recording_context' => [
                    'direction' => 'outbound',
                    'organization_id' => $organization->id,
                    'organization_policy' => $organization->recording_policy,
                    'did_id' => $did->id,
                    'did_policy' => $did->recording_policy,
                    'owner_extension_id' => $extension->id,
                    'extension_policy' => $extension->recording_policy,
                    'answered_target_type' => null,
                ],
            ]),
            'started_at' => $session->started_at ?? now(),
        ]);

        $session->save();
    }
}
