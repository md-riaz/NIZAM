<?php

namespace Tests\Unit\Events;

use App\Events\CallEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class CallEventTest extends TestCase
{
    public function test_broadcasts_on_tenant_channel(): void
    {
        $event = new CallEvent('tenant-uuid-123', 'call.created', ['caller' => '1001']);

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-tenant.tenant-uuid-123.calls', $channels[0]->name);
        $this->assertEquals('private-tenant.tenant-uuid-123.calls.call.created', $channels[1]->name);
    }

    public function test_broadcast_as_returns_prefixed_event_type(): void
    {
        $event = new CallEvent('tenant-uuid', 'call.answered', []);

        $this->assertEquals('call.answered', $event->broadcastAs());
    }

    public function test_event_stores_data(): void
    {
        $data = ['uuid' => 'call-123', 'caller_id_number' => '1001'];
        $event = new CallEvent('tenant-uuid', 'call.hangup', $data);

        $this->assertEquals('tenant-uuid', $event->tenantId);
        $this->assertEquals('call.hangup', $event->eventType);
        $this->assertEquals($data, $event->data);
    }

    public function test_different_event_types(): void
    {
        $types = ['call.created', 'call.answered', 'call.hangup', 'call.missed'];

        foreach ($types as $type) {
            $event = new CallEvent('t1', $type, []);
            $this->assertEquals($type, $event->broadcastAs());
        }
    }
}
