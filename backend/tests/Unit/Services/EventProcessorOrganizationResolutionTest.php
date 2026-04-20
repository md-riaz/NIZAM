<?php

namespace Tests\Unit\Services;

use App\Events\CallEvent;
use App\Models\CallEventLog;
use App\Models\Organization;
use App\Services\EventProcessor;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventProcessorOrganizationResolutionTest extends TestCase
{
    use RefreshDatabase;

    private EventProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->processor = new EventProcessor($dispatcher);
    }

    public function test_resolves_organization_by_domain_without_extensions(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'no-ext.example.com',
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
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

        $this->assertDatabaseHas('call_events', [
            'organization_id' => $organization->id,
            'call_uuid' => 'uuid-no-ext',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
        ]);
    }

    public function test_ignores_events_for_suspended_organization(): void
    {
        Organization::factory()->create([
            'domain' => 'suspended.example.com',
            'is_active' => false,
            'status' => Organization::STATUS_SUSPENDED,
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

    public function test_ignores_events_for_terminated_organization(): void
    {
        Organization::factory()->create([
            'domain' => 'terminated.example.com',
            'is_active' => false,
            'status' => Organization::STATUS_TERMINATED,
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
