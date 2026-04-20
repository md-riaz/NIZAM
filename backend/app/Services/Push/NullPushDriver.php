<?php

namespace App\Services\Push;

use App\Models\EndpointBinding;
use App\Services\Push\Contracts\PushDriver;

/**
 * No-op push driver.
 *
 * Used when PUSH_DRIVER=null, or as a safe fallback when push is not
 * configured. All sends are silently accepted without delivering anything.
 * Useful for organizations or environments where push is intentionally disabled.
 */
class NullPushDriver implements PushDriver
{
    public function send(EndpointBinding $binding, string $pushType, array $payload): PushDeliveryResult
    {
        return PushDeliveryResult::failed('push_driver_disabled');
    }
}
