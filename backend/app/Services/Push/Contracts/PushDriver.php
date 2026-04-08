<?php

namespace App\Services\Push\Contracts;

use App\Models\EndpointBinding;
use App\Services\Push\PushDeliveryResult;

interface PushDriver
{
    /**
     * Deliver a push notification to the given endpoint binding.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(EndpointBinding $binding, string $pushType, array $payload): PushDeliveryResult;
}
