<?php

namespace Tests\Unit\Services;

use App\Events\CallEvent;
use App\Models\CallEventLog;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\EventProcessor;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventProcessorEventLogTest extends TestCase
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

    public function test_channel_create_persists_call_event(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-create-log',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'tenant_id' => $tenant->id,
            'call_uuid' => 'uuid-create-log',
            'event_type' => 'started',
        ]);
    }

    public function test_channel_answer_persists_call_event(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-answer-log',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'call_uuid' => 'uuid-answer-log',
            'event_type' => 'answered',
        ]);
    }

    public function test_channel_bridge_persists_call_event(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-bridge-log',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Other-Leg-Unique-ID' => 'other-leg-123',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'call_uuid' => 'uuid-bridge-log',
            'event_type' => 'bridge',
        ]);
    }

    public function test_channel_hangup_persists_call_event(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-hangup-log',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_duration' => '60',
            'variable_billsec' => '55',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'call_uuid' => 'uuid-hangup-log',
            'event_type' => 'hangup',
        ]);
    }

    public function test_registration_persists_call_event(): void
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
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'registered',
        ]);
    }

    public function test_call_event_payload_contains_data(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-payload-check',
            'Caller-Caller-ID-Name' => 'Jane Doe',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '2001',
            'Call-Direction' => 'outbound',
        ];

        $this->processor->process($event);

        $logged = CallEventLog::where('call_uuid', 'uuid-payload-check')->first();
        $this->assertNotNull($logged);
        $this->assertEquals('Jane Doe', $logged->payload['caller_id_name']);
        $this->assertEquals('2001', $logged->payload['destination_number']);
    }

    public function test_full_call_lifecycle_creates_ordered_events(): void
    {
        [$tenant, $extension] = $this->createTenantWithExtension();
        Event::fake([CallEvent::class]);

        $uuid = 'uuid-lifecycle-test';

        $events = [
            ['Event-Name' => 'CHANNEL_CREATE', 'Unique-ID' => $uuid],
            ['Event-Name' => 'CHANNEL_ANSWER', 'Unique-ID' => $uuid],
            ['Event-Name' => 'CHANNEL_BRIDGE', 'Unique-ID' => $uuid, 'Other-Leg-Unique-ID' => 'leg-2'],
            ['Event-Name' => 'CHANNEL_HANGUP_COMPLETE', 'Unique-ID' => $uuid, 'Hangup-Cause' => 'NORMAL_CLEARING', 'variable_duration' => '30', 'variable_billsec' => '25'],
        ];

        foreach ($events as $event) {
            $event += [
                'variable_domain_name' => 'test.example.com',
                'Caller-Caller-ID-Name' => 'Test',
                'Caller-Caller-ID-Number' => '1001',
                'Caller-Destination-Number' => '1002',
                'Call-Direction' => 'inbound',
            ];
            $this->processor->process($event);
        }

        $loggedEvents = CallEventLog::where('call_uuid', $uuid)
            ->orderBy('occurred_at')
            ->get();

        $this->assertCount(4, $loggedEvents);
        $this->assertEquals('started', $loggedEvents[0]->event_type);
        $this->assertEquals('answered', $loggedEvents[1]->event_type);
        $this->assertEquals('bridge', $loggedEvents[2]->event_type);
        $this->assertEquals('hangup', $loggedEvents[3]->event_type);
    }
}
