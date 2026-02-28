<?php

namespace Tests\Unit\Services;

use App\Models\CallEventLog;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\EventProcessor;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventBusHardeningTest extends TestCase
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

    public function test_event_payload_includes_schema_version(): void
    {
        [$tenant] = $this->createTenantWithExtension();
        Event::fake();

        $this->processor->process([
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-schema-test',
            'Caller-Caller-ID-Name' => 'Test',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ]);

        $event = CallEventLog::where('call_uuid', 'uuid-schema-test')->first();
        $this->assertNotNull($event);
        $this->assertEquals(CallEventLog::SCHEMA_VERSION, $event->schema_version);
    }

    public function test_event_payload_has_immutable_format(): void
    {
        [$tenant] = $this->createTenantWithExtension();
        Event::fake();

        $this->processor->process([
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-format-test',
            'Caller-Caller-ID-Name' => 'Test',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ]);

        $event = CallEventLog::where('call_uuid', 'uuid-format-test')->first();
        $this->assertNotNull($event);

        $payload = $event->payload;
        $this->assertArrayHasKey('tenant_id', $payload);
        $this->assertArrayHasKey('call_uuid', $payload);
        $this->assertArrayHasKey('event_type', $payload);
        $this->assertArrayHasKey('timestamp', $payload);
        $this->assertArrayHasKey('schema_version', $payload);
        $this->assertArrayHasKey('metadata', $payload);
    }

    public function test_canonical_event_type_for_channel_create(): void
    {
        [$tenant] = $this->createTenantWithExtension();
        Event::fake();

        $this->processor->process([
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-canonical-create',
            'Caller-Caller-ID-Name' => 'Test',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ]);

        $this->assertDatabaseHas('call_events', [
            'call_uuid' => 'uuid-canonical-create',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
        ]);
    }

    public function test_canonical_event_types_are_defined(): void
    {
        $this->assertContains('call.created', CallEventLog::CANONICAL_EVENTS);
        $this->assertContains('call.answered', CallEventLog::CANONICAL_EVENTS);
        $this->assertContains('call.bridged', CallEventLog::CANONICAL_EVENTS);
        $this->assertContains('call.hangup', CallEventLog::CANONICAL_EVENTS);
        $this->assertContains('voicemail.received', CallEventLog::CANONICAL_EVENTS);
        $this->assertContains('device.registered', CallEventLog::CANONICAL_EVENTS);
        $this->assertContains('device.unregistered', CallEventLog::CANONICAL_EVENTS);
    }

    public function test_event_payload_metadata_contains_call_data(): void
    {
        [$tenant] = $this->createTenantWithExtension();
        Event::fake();

        $this->processor->process([
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-metadata-test',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '2002',
            'Call-Direction' => 'outbound',
        ]);

        $event = CallEventLog::where('call_uuid', 'uuid-metadata-test')->first();
        $metadata = $event->payload['metadata'];

        $this->assertEquals('uuid-metadata-test', $metadata['uuid']);
        $this->assertEquals('John', $metadata['caller_id_name']);
        $this->assertEquals('1001', $metadata['caller_id_number']);
        $this->assertEquals('2002', $metadata['destination_number']);
        $this->assertEquals('outbound', $metadata['direction']);
    }
}
