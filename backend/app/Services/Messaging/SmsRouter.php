<?php

namespace App\Services\Messaging;

class SmsRouter
{
    /**
     * @param  array<string, SmsAdapterInterface>  $adapters
     */
    public function __construct(
        private readonly array $adapters,
    ) {}

    public function route(SmsSendRequest $request, ?string $preferredProvider = null): SmsRoute
    {
        if ($preferredProvider !== null) {
            $preferredAdapter = $this->adapters[$preferredProvider] ?? null;

            if ($preferredAdapter !== null && $preferredAdapter->supportsOutbound()) {
                return new SmsRoute($preferredProvider, ['strategy' => 'preferred_provider']);
            }
        }

        foreach ($this->adapters as $name => $adapter) {
            if ($adapter->supportsOutbound()) {
                return new SmsRoute($name, ['strategy' => 'first_available']);
            }
        }

        return new SmsRoute(null, ['strategy' => 'no_available_provider']);
    }

    public function adapterFor(SmsRoute $route): ?SmsAdapterInterface
    {
        if ($route->provider === null) {
            return null;
        }

        return $this->adapters[$route->provider] ?? null;
    }
}
