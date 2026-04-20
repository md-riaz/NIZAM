<?php

namespace Tests\Unit\Modules;

use App\Modules\Messaging\MessagingModule;
use App\Services\Messaging\DatabaseSmsMessageStore;
use App\Services\Messaging\InMemorySmsMessageStore;
use App\Services\Messaging\SignalWireSmsAdapter;
use App\Services\Messaging\SmsMessageStoreInterface;
use App\Services\Messaging\SmsRouter;
use App\Services\Messaging\SmsService;
use App\Services\Messaging\TelnyxSmsAdapter;
use Tests\TestCase;

class MessagingModuleTest extends TestCase
{
    public function test_module_manifest_and_permissions(): void
    {
        $module = new MessagingModule;

        $this->assertSame('messaging', $module->name());
        $this->assertSame('1.0.0', $module->version());
        $this->assertNotEmpty($module->description());
        $this->assertContains('messaging.sms.view', $module->permissions());
        $this->assertContains('messaging.sms.send', $module->permissions());
        $this->assertContains('messaging.sms.outbound.requested', $module->subscribedEvents());
        $this->assertContains('messaging.sms.inbound.received', $module->subscribedEvents());
        $this->assertNull($module->routesFile());
    }

    public function test_module_registers_sms_services_into_container(): void
    {
        config(['services.messaging.store' => 'memory']);

        $module = new MessagingModule;
        $module->register();

        $this->assertInstanceOf(SmsMessageStoreInterface::class, app(SmsMessageStoreInterface::class));
        $this->assertInstanceOf(InMemorySmsMessageStore::class, app(SmsMessageStoreInterface::class));
        $this->assertInstanceOf(SmsRouter::class, app(SmsRouter::class));
        $this->assertInstanceOf(SmsService::class, app(SmsService::class));
        $this->assertInstanceOf(SignalWireSmsAdapter::class, app(SignalWireSmsAdapter::class));
        $this->assertInstanceOf(TelnyxSmsAdapter::class, app(TelnyxSmsAdapter::class));
        $this->assertFalse(app()->bound('App\\Services\\Messaging\\SmsAdapterInterface'));
    }

    public function test_module_uses_database_store_by_default_outside_testing_override(): void
    {
        config(['services.messaging.store' => 'database']);

        $module = new MessagingModule;
        $module->register();

        $this->assertInstanceOf(DatabaseSmsMessageStore::class, app(SmsMessageStoreInterface::class));
    }

    public function test_router_registers_providers_by_adapter_name(): void
    {
        config(['services.messaging.store' => 'memory']);

        $module = new MessagingModule;
        $module->register();

        $router = app(SmsRouter::class);

        $this->assertInstanceOf(SignalWireSmsAdapter::class, $router->adapterFor($router->route(new \App\Services\Messaging\SmsSendRequest('organization.example.com', '+1', '+2', 'hello'), 'signalwire')));
        $this->assertInstanceOf(TelnyxSmsAdapter::class, $router->adapterFor($router->route(new \App\Services\Messaging\SmsSendRequest('organization.example.com', '+1', '+2', 'hello'), 'telnyx')));
    }
}
