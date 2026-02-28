<?php

namespace Tests\Unit\Services;

use App\Events\CallEvent;
use App\Models\Tenant;
use App\Services\EventProcessor;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventProcessorTenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    private EventProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->processor = new EventProcessor($dispatcher);
    }

    public function test_resolves_tenant_by_domain_without_extensions(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'no-ext.example.com',
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'no-ext.example.com',
            'Unique-ID' => 'uuid-no-ext',
            'Caller-Caller-ID-Name' => 'Caller',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($tenant) {
            return $e->tenantId === $tenant->id && $e->eventType === 'started';
        });
    }

    public function test_ignores_events_for_suspended_tenant(): void
    {
        Tenant::factory()->create([
            'domain' => 'suspended.example.com',
            'is_active' => false,
            'status' => Tenant::STATUS_SUSPENDED,
        ]);

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'suspended.example.com',
            'Unique-ID' => 'uuid-suspended',
            'Caller-Caller-ID-Name' => 'Caller',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        Event::assertNotDispatched(CallEvent::class);
    }

    public function test_ignores_events_for_terminated_tenant(): void
    {
        Tenant::factory()->create([
            'domain' => 'terminated.example.com',
            'is_active' => false,
            'status' => Tenant::STATUS_TERMINATED,
        ]);

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'terminated.example.com',
            'Unique-ID' => 'uuid-terminated',
            'Caller-Caller-ID-Name' => 'Caller',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        Event::assertNotDispatched(CallEvent::class);
    }
}
