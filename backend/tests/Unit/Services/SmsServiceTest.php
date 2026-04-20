<?php

namespace Tests\Unit\Services;

use App\Services\Messaging\InMemorySmsMessageStore;
use App\Services\Messaging\SmsAdapterInterface;
use App\Services\Messaging\SmsMessageRecord;
use App\Services\Messaging\SmsRouter;
use App\Services\Messaging\SmsSendRequest;
use App\Services\Messaging\SmsSendResult;
use App\Services\Messaging\SmsService;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    public function test_it_routes_to_preferred_provider_and_records_sent_message(): void
    {
        $router = new SmsRouter([
            'signalwire' => new class implements SmsAdapterInterface {
                public function name(): string
                {
                    return 'signalwire';
                }

                public function supportsOutbound(): bool
                {
                    return true;
                }

                public function send(SmsSendRequest $request): SmsSendResult
                {
                    return SmsSendResult::sent('sw-123', ['provider' => 'signalwire']);
                }
            },
            'telnyx' => new class implements SmsAdapterInterface {
                public function name(): string
                {
                    return 'telnyx';
                }

                public function supportsOutbound(): bool
                {
                    return true;
                }

                public function send(SmsSendRequest $request): SmsSendResult
                {
                    return SmsSendResult::sent('tx-456');
                }
            },
        ]);

        $store = new InMemorySmsMessageStore;
        $service = new SmsService($router, $store);

        $message = $service->send(new SmsSendRequest(
            organizationDomain: 'organization.example.com',
            from: '+15550000001',
            to: '+15550000002',
            body: 'Hello world',
            metadata: ['conversation_id' => 'abc'],
        ), 'telnyx');

        $this->assertSame(SmsMessageRecord::STATUS_SENT, $message->status);
        $this->assertSame('telnyx', $message->provider);
        $this->assertSame('tx-456', $message->providerMessageId);
        $this->assertSame('preferred_provider', $message->metadata['route']['strategy']);
        $this->assertCount(1, $service->historyForOrganization('organization.example.com'));
    }

    public function test_it_uses_first_available_provider_when_no_preference_is_given(): void
    {
        $router = new SmsRouter([
            'signalwire' => new class implements SmsAdapterInterface {
                public function name(): string
                {
                    return 'signalwire';
                }

                public function supportsOutbound(): bool
                {
                    return true;
                }

                public function send(SmsSendRequest $request): SmsSendResult
                {
                    return SmsSendResult::sent('sw-001');
                }
            },
        ]);

        $service = new SmsService($router, new InMemorySmsMessageStore);

        $message = $service->send(new SmsSendRequest(
            organizationDomain: 'organization.example.com',
            from: '+15550000001',
            to: '+15550000003',
            body: 'Fallback route',
        ));

        $this->assertSame(SmsMessageRecord::STATUS_SENT, $message->status);
        $this->assertSame('signalwire', $message->provider);
        $this->assertSame('first_available', $message->metadata['route']['strategy']);
    }

    public function test_it_skips_preferred_provider_that_does_not_support_outbound(): void
    {
        $router = new SmsRouter([
            'signalwire' => new class implements SmsAdapterInterface {
                public function name(): string
                {
                    return 'signalwire';
                }

                public function supportsOutbound(): bool
                {
                    return false;
                }

                public function send(SmsSendRequest $request): SmsSendResult
                {
                    return SmsSendResult::sent('sw-should-not-send');
                }
            },
            'telnyx' => new class implements SmsAdapterInterface {
                public function name(): string
                {
                    return 'telnyx';
                }

                public function supportsOutbound(): bool
                {
                    return true;
                }

                public function send(SmsSendRequest $request): SmsSendResult
                {
                    return SmsSendResult::sent('tx-fallback');
                }
            },
        ]);

        $service = new SmsService($router, new InMemorySmsMessageStore);

        $message = $service->send(new SmsSendRequest(
            organizationDomain: 'organization.example.com',
            from: '+15550000001',
            to: '+15550000005',
            body: 'Preferred provider unsupported',
        ), 'signalwire');

        $this->assertSame(SmsMessageRecord::STATUS_SENT, $message->status);
        $this->assertSame('telnyx', $message->provider);
        $this->assertSame('first_available', $message->metadata['route']['strategy']);
    }

    public function test_it_returns_no_available_provider_when_preferred_provider_is_unsupported_and_no_fallback_exists(): void
    {
        $router = new SmsRouter([
            'signalwire' => new class implements SmsAdapterInterface {
                public function name(): string
                {
                    return 'signalwire';
                }

                public function supportsOutbound(): bool
                {
                    return false;
                }

                public function send(SmsSendRequest $request): SmsSendResult
                {
                    return SmsSendResult::sent('sw-should-not-send');
                }
            },
        ]);

        $service = new SmsService($router, new InMemorySmsMessageStore);

        $message = $service->send(new SmsSendRequest(
            organizationDomain: 'organization.example.com',
            from: '+15550000001',
            to: '+15550000006',
            body: 'No outbound provider',
        ), 'signalwire');

        $this->assertSame(SmsMessageRecord::STATUS_FAILED, $message->status);
        $this->assertNull($message->provider);
        $this->assertSame('no_available_provider', $message->metadata['route']['strategy']);
    }

    public function test_router_ignores_unknown_preferred_provider_and_uses_available_adapter(): void
    {
        $router = new SmsRouter([
            'telnyx' => new class implements SmsAdapterInterface {
                public function name(): string
                {
                    return 'telnyx';
                }

                public function supportsOutbound(): bool
                {
                    return true;
                }

                public function send(SmsSendRequest $request): SmsSendResult
                {
                    return SmsSendResult::sent('tx-unknown-preferred');
                }
            },
        ]);

        $service = new SmsService($router, new InMemorySmsMessageStore);

        $message = $service->send(new SmsSendRequest(
            organizationDomain: 'organization.example.com',
            from: '+15550000001',
            to: '+15550000007',
            body: 'Unknown preferred provider',
        ), 'unknown');

        $this->assertSame(SmsMessageRecord::STATUS_SENT, $message->status);
        $this->assertSame('telnyx', $message->provider);
        $this->assertSame('first_available', $message->metadata['route']['strategy']);
    }

    public function test_it_records_failure_when_no_provider_is_available(): void
    {
        $router = new SmsRouter([]);
        $service = new SmsService($router, new InMemorySmsMessageStore);

        $message = $service->send(new SmsSendRequest(
            organizationDomain: 'organization.example.com',
            from: '+15550000001',
            to: '+15550000004',
            body: 'No provider',
        ));

        $this->assertSame(SmsMessageRecord::STATUS_FAILED, $message->status);
        $this->assertNull($message->provider);
        $this->assertSame('no_sms_provider_available', $message->failureReason);
        $this->assertSame('no_available_provider', $message->metadata['route']['strategy']);
    }

    public function test_store_filters_history_by_organization_domain(): void
    {
        $router = new SmsRouter([
            'signalwire' => new class implements SmsAdapterInterface {
                public function name(): string
                {
                    return 'signalwire';
                }

                public function supportsOutbound(): bool
                {
                    return true;
                }

                public function send(SmsSendRequest $request): SmsSendResult
                {
                    return SmsSendResult::sent('sw-history');
                }
            },
        ]);

        $service = new SmsService($router, new InMemorySmsMessageStore);

        $service->send(new SmsSendRequest('a.example.com', '+1', '+2', 'A'));
        $service->send(new SmsSendRequest('b.example.com', '+1', '+3', 'B'));

        $this->assertCount(1, $service->historyForOrganization('a.example.com'));
        $this->assertCount(1, $service->historyForOrganization('b.example.com'));
    }
}
