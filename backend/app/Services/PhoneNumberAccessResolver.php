<?php

namespace App\Services;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Gateway;
use InvalidArgumentException;

class PhoneNumberAccessResolver
{
    /**
     * @return array{did:?Did,gateway:?Gateway}
     */
    public function resolve(
        Extension $extension,
        ?string $requestedDidId = null,
        ?string $requestedGatewayId = null,
    ): array {
        $extension->loadMissing([
            'organization',
            'defaultOutboundDid.gateway',
            'defaultOutboundGateway',
            'allowedOutboundDids.gateway',
            'allowedOutboundGateways',
        ]);

        $did = $this->resolveDid($extension, $requestedDidId);
        $gateway = $this->resolveGateway($extension, $did, $requestedGatewayId);

        return [
            'did' => $did,
            'gateway' => $gateway,
        ];
    }

    protected function resolveDid(Extension $extension, ?string $requestedDidId): ?Did
    {
        if ($requestedDidId === null) {
            $did = $extension->defaultOutboundDid;

            if ($did !== null) {
                if (! $extension->hasAllowedOutboundDid($did->id)) {
                    throw new InvalidArgumentException('The default outbound DID is not allowed for this extension.');
                }

                if ($did->organization_id !== $extension->organization_id || ! $did->is_active) {
                    throw new InvalidArgumentException('The default outbound DID is invalid for this organization.');
                }

                return $did->loadMissing('gateway');
            }

            return $extension->allowedOutboundDids()
                ->where('dids.organization_id', $extension->organization_id)
                ->where('dids.is_active', true)
                ->with('gateway')
                ->orderBy('dids.number')
                ->first();
        }

        if (! $extension->hasAllowedOutboundDid($requestedDidId)) {
            throw new InvalidArgumentException('The requested outbound DID is not allowed for this extension.');
        }

        $did = $extension->allowedOutboundDids()
            ->where('dids.organization_id', $extension->organization_id)
            ->where('dids.is_active', true)
            ->with('gateway')
            ->find($requestedDidId);

        if (! $did) {
            throw new InvalidArgumentException('The requested outbound DID is invalid for this organization.');
        }

        return $did;
    }

    protected function resolveGateway(Extension $extension, ?Did $did, ?string $requestedGatewayId): ?Gateway
    {
        $didGatewayId = $did?->gateway_id;

        if ($requestedGatewayId !== null) {
            if (! $extension->hasAllowedOutboundGateway($requestedGatewayId)) {
                throw new InvalidArgumentException('The requested outbound gateway is not allowed for this extension.');
            }

            $gateway = $extension->allowedOutboundGateways()
                ->where('gateways.organization_id', $extension->organization_id)
                ->where('gateways.is_active', true)
                ->find($requestedGatewayId);

            if (! $gateway) {
                throw new InvalidArgumentException('The requested outbound gateway is invalid for this organization.');
            }

            if ($didGatewayId !== null && $didGatewayId !== $gateway->id) {
                throw new InvalidArgumentException('The selected outbound DID is linked to a different gateway.');
            }

            return $gateway;
        }

        $defaultGateway = $extension->defaultOutboundGateway;

        if ($defaultGateway !== null) {
            if (! $extension->hasAllowedOutboundGateway($defaultGateway->id)) {
                throw new InvalidArgumentException('The default outbound gateway is not allowed for this extension.');
            }

            if ($defaultGateway->organization_id !== $extension->organization_id || ! $defaultGateway->is_active) {
                throw new InvalidArgumentException('The default outbound gateway is invalid for this organization.');
            }
        }

        if ($didGatewayId !== null) {
            if ($defaultGateway !== null && $defaultGateway->id !== $didGatewayId) {
                throw new InvalidArgumentException('The default outbound gateway conflicts with the selected outbound DID gateway.');
            }

            return $did->gateway;
        }

        return $defaultGateway;
    }
}
