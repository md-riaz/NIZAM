<?php

namespace Tests\Unit\Events;

use App\Events\CallEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class CallEventBroadcastTest extends TestCase
{
    public function test_call_event_broadcasts_on_private_organization_channel(): void
    {
        $event = new CallEvent(
            organizationId: 'organization-123',
            eventType: 'call.created',
            data: ['uuid' => 'call-456']
        );

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
        $this->assertEquals('private-organization.organization-123.calls', $channels[0]->name);
        $this->assertEquals('private-organization.organization-123.calls.call.created', $channels[1]->name);
    }

    public function test_call_event_broadcast_name(): void
    {
        $event = new CallEvent(
            organizationId: 'organization-123',
            eventType: 'call.hangup',
            data: ['uuid' => 'call-456']
        );

        $this->assertEquals('call.hangup', $event->broadcastAs());
    }
}
