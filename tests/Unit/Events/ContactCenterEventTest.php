<?php

namespace Tests\Unit\Events;

use App\Events\ContactCenterEvent;
use App\Models\CallEventLog;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class ContactCenterEventTest extends TestCase
{
    public function test_broadcasts_on_correct_channels(): void
    {
        $event = new ContactCenterEvent(
            tenantId: 'tenant-123',
            eventType: 'agent.state_changed',
            data: ['agent_id' => 'agent-1', 'state' => 'available']
        );

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
        $this->assertEquals('private-tenant.tenant-123.contact-center', $channels[0]->name);
        $this->assertEquals('private-tenant.tenant-123.contact-center.agent-state_changed', $channels[1]->name);
    }

    public function test_broadcast_as_returns_correct_event_name(): void
    {
        $event = new ContactCenterEvent(
            tenantId: 'tenant-123',
            eventType: 'agent.state_changed',
            data: []
        );

        $this->assertEquals('contact-center.agent.state_changed', $event->broadcastAs());
    }

    public function test_broadcast_with_includes_schema_version(): void
    {
        $data = ['agent_id' => 'agent-1'];
        $event = new ContactCenterEvent(
            tenantId: 'tenant-123',
            eventType: 'agent.state_changed',
            data: $data
        );

        $broadcastData = $event->broadcastWith();

        $this->assertEquals('agent.state_changed', $broadcastData['event_type']);
        $this->assertEquals(CallEventLog::SCHEMA_VERSION, $broadcastData['schema_version']);
        $this->assertEquals($data, $broadcastData['data']);
    }

    public function test_queue_event_types(): void
    {
        $types = [
            'queue.call_joined',
            'queue.call_answered',
            'queue.call_abandoned',
            'queue.call_overflowed',
        ];

        foreach ($types as $type) {
            $event = new ContactCenterEvent('t1', $type, []);
            $this->assertEquals("contact-center.{$type}", $event->broadcastAs());
        }
    }

    public function test_filtered_channel_uses_hyphenated_event_type(): void
    {
        $event = new ContactCenterEvent('t1', 'queue.call_joined', []);
        $channels = $event->broadcastOn();
        $this->assertEquals('private-tenant.t1.contact-center.queue-call_joined', $channels[1]->name);

        $event2 = new ContactCenterEvent('t1', 'agent.state_changed', []);
        $channels2 = $event2->broadcastOn();
        $this->assertEquals('private-tenant.t1.contact-center.agent-state_changed', $channels2[1]->name);
    }
}
