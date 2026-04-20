<?php

namespace App\Services\Messaging;

use Illuminate\Support\Str;

class SmsService
{
    public function __construct(
        private readonly SmsRouter $router,
        private readonly SmsMessageStoreInterface $store,
    ) {}

    public function send(SmsSendRequest $request, ?string $preferredProvider = null): SmsMessageRecord
    {
        $route = $this->router->route($request, $preferredProvider);
        $adapter = $this->router->adapterFor($route);

        if ($adapter === null) {
            return $this->store->store(new SmsMessageRecord(
                id: (string) Str::uuid(),
                organizationDomain: $request->organizationDomain,
                direction: SmsMessageRecord::DIRECTION_OUTBOUND,
                from: $request->from,
                to: $request->to,
                body: $request->body,
                status: SmsMessageRecord::STATUS_FAILED,
                provider: null,
                providerMessageId: null,
                failureReason: 'no_sms_provider_available',
                metadata: [
                    'route' => $route->metadata,
                    'request' => $request->metadata,
                ],
                createdAt: now()->toDateTimeImmutable(),
            ));
        }

        $result = $adapter->send($request);

        return $this->store->store(new SmsMessageRecord(
            id: (string) Str::uuid(),
            organizationDomain: $request->organizationDomain,
            direction: SmsMessageRecord::DIRECTION_OUTBOUND,
            from: $request->from,
            to: $request->to,
            body: $request->body,
            status: $result->sent ? SmsMessageRecord::STATUS_SENT : SmsMessageRecord::STATUS_FAILED,
            provider: $adapter->name(),
            providerMessageId: $result->providerMessageId,
            failureReason: $result->failureReason,
            metadata: [
                'route' => $route->metadata,
                'request' => $request->metadata,
                'provider' => $result->metadata,
            ],
            createdAt: now()->toDateTimeImmutable(),
        ));
    }

    /**
     * @return array<SmsMessageRecord>
     */
    public function historyForOrganization(string $organizationDomain): array
    {
        return $this->store->forOrganization($organizationDomain);
    }
}
