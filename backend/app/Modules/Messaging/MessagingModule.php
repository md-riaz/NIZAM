<?php

namespace App\Modules\Messaging;

use App\Modules\BaseModule;
use App\Services\Messaging\DatabaseSmsMessageStore;
use App\Services\Messaging\InMemorySmsMessageStore;
use App\Services\Messaging\SignalWireSmsAdapter;
use App\Services\Messaging\SmsAdapterInterface;
use App\Services\Messaging\SmsMessageStoreInterface;
use App\Services\Messaging\SmsRouter;
use App\Services\Messaging\SmsService;
use App\Services\Messaging\TelnyxSmsAdapter;

class MessagingModule extends BaseModule
{
    public function name(): string
    {
        return 'messaging';
    }

    public function description(): string
    {
        return 'Provider-neutral messaging scaffolding for outbound SMS routing and storage.';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(): void
    {
        foreach ($this->providerAdapters() as $adapterClass) {
            app()->singleton($adapterClass, fn () => new $adapterClass);
        }

        app()->singleton(SmsMessageStoreInterface::class, function (): SmsMessageStoreInterface {
            $store = (string) config('services.messaging.store', app()->environment('testing') ? 'memory' : 'database');

            return match ($store) {
                'memory' => new InMemorySmsMessageStore,
                default => new DatabaseSmsMessageStore,
            };
        });

        app()->singleton(SmsRouter::class, function (): SmsRouter {
            $adapters = [];

            foreach ($this->providerAdapters() as $adapterClass) {
                /** @var SmsAdapterInterface $adapter */
                $adapter = app($adapterClass);
                $adapters[$adapter->name()] = $adapter;
            }

            return new SmsRouter($adapters);
        });

        app()->singleton(SmsService::class, function (): SmsService {
            return new SmsService(
                app(SmsRouter::class),
                app(SmsMessageStoreInterface::class),
            );
        });
    }

    /**
     * @return list<class-string<SmsAdapterInterface>>
     */
    private function providerAdapters(): array
    {
        return [
            SignalWireSmsAdapter::class,
            TelnyxSmsAdapter::class,
        ];
    }

    public function subscribedEvents(): array
    {
        return [
            'messaging.sms.outbound.requested',
            'messaging.sms.inbound.received',
        ];
    }

    public function permissions(): array
    {
        return [
            'messaging.sms.view',
            'messaging.sms.send',
        ];
    }
}
