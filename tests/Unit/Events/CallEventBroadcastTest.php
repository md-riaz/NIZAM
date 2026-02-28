<?php

namespace Tests\Unit\Events;

use App\Events\CallEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class CallEventBroadcastTest extends TestCase
{
    public function test_call_event_broadcasts_on_private_tenant_channel(): void
    {
        $event = new CallEvent(
            tenantId: 'tenant-123',
            eventType: 'started',
            data: ['uuid' => 'call-456']
        );

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
    }

    public function test_call_event_broadcast_name(): void
    {
        $event = new CallEvent(
            tenantId: 'tenant-123',
            eventType: 'hangup',
            data: ['uuid' => 'call-456']
        );

        $this->assertEquals('call.hangup', $event->broadcastAs());
    }
}
