<?php

namespace Tests\Unit\Events;

use App\Events\CallEvent;
use Illuminate\Broadcasting\Channel;
use Tests\TestCase;

class CallEventTest extends TestCase
{
    public function test_broadcasts_on_tenant_channel(): void
    {
        $event = new CallEvent('tenant-uuid-123', 'started', ['caller' => '1001']);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertEquals('tenant.tenant-uuid-123.calls', $channels[0]->name);
    }

    public function test_broadcast_as_returns_prefixed_event_type(): void
    {
        $event = new CallEvent('tenant-uuid', 'answered', []);

        $this->assertEquals('call.answered', $event->broadcastAs());
    }

    public function test_event_stores_data(): void
    {
        $data = ['uuid' => 'call-123', 'caller_id_number' => '1001'];
        $event = new CallEvent('tenant-uuid', 'hangup', $data);

        $this->assertEquals('tenant-uuid', $event->tenantId);
        $this->assertEquals('hangup', $event->eventType);
        $this->assertEquals($data, $event->data);
    }

    public function test_different_event_types(): void
    {
        $types = ['started', 'answered', 'hangup', 'missed'];

        foreach ($types as $type) {
            $event = new CallEvent('t1', $type, []);
            $this->assertEquals('call.'.$type, $event->broadcastAs());
        }
    }
}
