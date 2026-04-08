<?php

namespace App\Services\Push;

use App\Models\EndpointBinding;
use App\Services\Push\Contracts\PushDriver;

/**
 * Resolves and invokes the correct push driver for a given endpoint binding.
 *
 * Driver selection order:
 *   1. iOS bindings with a VoIP push token → ApnsPushDriver
 *   2. Android / web bindings with a data push token → FcmPushDriver
 *   3. iOS bindings with only a data push token (no VoIP token) → FcmPushDriver
 *   4. Fallback → NullPushDriver (returns a failed result with 'no_driver')
 *
 * The driver instances are resolved from the Laravel service container so
 * they can be easily swapped in tests via app()->instance().
 */
class PushDriverManager
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function deliver(EndpointBinding $binding, string $pushType, array $payload): PushDeliveryResult
    {
        $driver = $this->resolveDriver($binding);

        return $driver->send($binding, $pushType, $payload);
    }

    protected function resolveDriver(EndpointBinding $binding): PushDriver
    {
        $platform = $binding->platform ?? EndpointBinding::PLATFORM_UNKNOWN;

        // iOS with VoIP token → APNs VoIP (required for CallKit)
        if ($platform === EndpointBinding::PLATFORM_IOS && filled($binding->voip_push_token)) {
            return app(ApnsPushDriver::class);
        }

        // Android / web → FCM
        if (in_array($platform, [EndpointBinding::PLATFORM_ANDROID, EndpointBinding::PLATFORM_WEB], true)
            && filled($binding->push_token)) {
            return app(FcmPushDriver::class);
        }

        // iOS with only a data push token (no VoIP token) → FCM
        if ($platform === EndpointBinding::PLATFORM_IOS && filled($binding->push_token)) {
            return app(FcmPushDriver::class);
        }

        // Any platform with a data token as last resort
        if (filled($binding->push_token)) {
            return app(FcmPushDriver::class);
        }

        // Any platform with only a VoIP token as last resort
        if (filled($binding->voip_push_token)) {
            return app(ApnsPushDriver::class);
        }

        return app(NullPushDriver::class);
    }
}
