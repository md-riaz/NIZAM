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

class EventProcessorTest extends TestCase
{
    use RefreshDatabase;

    private EventProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->processor = new EventProcessor($dispatcher);
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

    public function test_processes_channel_hangup_complete_and_creates_cdr(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-123',
            'Caller-Caller-ID-Name' => 'John Doe',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_duration' => '60',
            'variable_billsec' => '55',
            'variable_start_stamp' => '2024-01-01 10:00:00',
            'variable_answer_stamp' => '2024-01-01 10:00:05',
            'variable_end_stamp' => '2024-01-01 10:01:00',
            'Caller-Context' => 'default',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_detail_records', [
            'uuid' => 'test-uuid-123',
            'tenant_id' => $tenant->id,
            'caller_id_number' => '1001',
            'destination_number' => '1002',
            'hangup_cause' => 'NORMAL_CLEARING',
        ]);
    }

    public function test_dispatches_call_event_on_channel_create(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-456',
            'Caller-Caller-ID-Name' => 'Jane Doe',
            'Caller-Caller-ID-Number' => '1002',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($tenant) {
            return $e->tenantId === $tenant->id && $e->eventType === 'started';
        });
    }

    public function test_dispatches_call_event_on_channel_answer(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-789',
            'Caller-Caller-ID-Name' => 'Jane Doe',
            'Caller-Caller-ID-Number' => '1002',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($tenant) {
            return $e->tenantId === $tenant->id && $e->eventType === 'answered';
        });
    }

    public function test_dispatches_missed_call_webhook_on_no_answer(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($tenantId, $eventType, $payload) use ($tenant) {
                $this->assertEquals($tenant->id, $tenantId);
                $this->assertContains($eventType, ['call.hangup', 'call.missed']);
            });

        $processor = new EventProcessor($dispatcher);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-missed',
            'Caller-Caller-ID-Name' => 'Missed Call',
            'Caller-Caller-ID-Number' => '5551234567',
            'Caller-Destination-Number' => '1001',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NO_ANSWER',
            'variable_duration' => '30',
            'variable_billsec' => '0',
        ];

        $processor->process($event);
    }

    public function test_ignores_events_without_tenant_domain(): void
    {
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'Unique-ID' => 'test-uuid-unknown',
            'Caller-Caller-ID-Name' => 'Unknown',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        Event::assertNotDispatched(CallEvent::class);
    }

    public function test_handles_unknown_event_types_gracefully(): void
    {
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'SOME_UNKNOWN_EVENT',
            'variable_domain_name' => 'test.example.com',
        ];

        // Should not throw exception
        $this->processor->process($event);

        Event::assertNotDispatched(CallEvent::class);
    }
}
