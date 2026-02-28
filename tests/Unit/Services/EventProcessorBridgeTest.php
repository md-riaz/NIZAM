<?php

namespace Tests\Unit\Services;

use App\Events\CallEvent;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\EventProcessor;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventProcessorBridgeTest extends TestCase
{
    use RefreshDatabase;

    private EventProcessor $processor;

    private WebhookDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->processor = new EventProcessor($this->dispatcher);
    }

    private function createTenantWithExtension(): array
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'is_active' => true,
        ]);

        return [$tenant, $extension];
    }

    public function test_dispatches_bridge_event_on_channel_bridge(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-bridge',
            'Caller-Caller-ID-Name' => 'John Doe',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Other-Leg-Unique-ID' => 'test-uuid-other-leg',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($tenant) {
            return $e->tenantId === $tenant->id && $e->eventType === 'bridge';
        });
    }

    public function test_bridge_event_includes_other_leg_uuid(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-bridge-2',
            'Caller-Caller-ID-Name' => 'John Doe',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Other-Leg-Unique-ID' => 'other-leg-uuid-123',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) {
            return $e->eventType === 'bridge'
                && isset($e->data['other_leg_uuid'])
                && $e->data['other_leg_uuid'] === 'other-leg-uuid-123';
        });
    }

    public function test_bridge_event_dispatches_webhook(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $this->dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($tenant->id, 'call.bridge', $this->anything());

        $processor = new EventProcessor($this->dispatcher);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-bridge-3',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Other-Leg-Unique-ID' => 'other-leg-uuid',
        ];

        $processor->process($event);
    }

    public function test_handles_registration_event(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::register',
            'domain' => 'test.example.com',
            'from-user' => '1001',
            'contact' => 'sip:1001@192.168.1.100:5060',
            'user-agent' => 'Yealink SIP-T54W',
            'network-ip' => '192.168.1.100',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($tenant) {
            return $e->tenantId === $tenant->id && $e->eventType === 'registered';
        });
    }

    public function test_handles_unregistration_event(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::unregister',
            'domain' => 'test.example.com',
            'from-user' => '1001',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($tenant) {
            return $e->tenantId === $tenant->id && $e->eventType === 'unregistered';
        });
    }

    public function test_registration_dispatches_webhook(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);
        Event::fake([CallEvent::class]);

        $this->dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($tenant->id, 'registration.registered', $this->anything());

        $processor = new EventProcessor($this->dispatcher);

        $event = [
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::register',
            'domain' => 'test.example.com',
            'from-user' => '1001',
        ];

        $processor->process($event);
    }

    public function test_ignores_registration_for_unknown_domain(): void
    {
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::register',
            'domain' => 'unknown.example.com',
            'from-user' => '1001',
        ];

        $this->processor->process($event);

        Event::assertNotDispatched(CallEvent::class);
    }
}
