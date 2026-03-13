<?php

namespace App\Services\Media;

use App\Models\Gateway;
use App\Models\GatewayRegistration;

class GatewayRegistrationService
{
    public function findOrCreate(Gateway $gateway, array $attributes): GatewayRegistration
    {
        return GatewayRegistration::updateOrCreate(
            [
                'registration_identifier' => $attributes['registration_identifier'],
            ],
            [
                'gateway_id' => $gateway->id,
                'username' => $attributes['username'] ?? null,
                'realm' => $attributes['realm'] ?? null,
                'proxy' => $attributes['proxy'] ?? null,
                'transport' => $attributes['transport'] ?? null,
                'status' => $attributes['status'] ?? 'unknown',
                'last_registered_at' => $attributes['last_registered_at'] ?? null,
                'last_failed_at' => $attributes['last_failed_at'] ?? null,
                'meta' => $attributes['meta'] ?? null,
            ]
        );
    }
}
