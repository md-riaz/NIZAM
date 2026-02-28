<?php

namespace Tests\Unit\Events;

use App\Events\CallEvent;
use App\Models\CallEventLog;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class CallEventFilteredBroadcastTest extends TestCase
{
    public function test_broadcasts_on_event_type_specific_channel(): void
    {
        $event = new CallEvent('tenant-123', 'hangup', ['uuid' => 'call-456']);

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
        $this->assertEquals('private-tenant.tenant-123.calls.hangup', $channels[1]->name);
    }

    public function test_broadcast_with_includes_schema_version(): void
    {
        $event = new CallEvent('tenant-123', 'started', ['uuid' => 'call-789']);

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('schema_version', $broadcastData);
        $this->assertEquals(CallEventLog::SCHEMA_VERSION, $broadcastData['schema_version']);
        $this->assertEquals('started', $broadcastData['event_type']);
    }

    public function test_different_event_types_broadcast_on_different_channels(): void
    {
        $eventTypes = ['started', 'answered', 'hangup', 'bridge'];

        foreach ($eventTypes as $type) {
            $event = new CallEvent('tenant-abc', $type, []);
            $channels = $event->broadcastOn();

            $this->assertEquals(
                "private-tenant.tenant-abc.calls.{$type}",
                $channels[1]->name
            );
        }
    }
}
