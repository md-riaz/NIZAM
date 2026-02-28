<?php

namespace App\Events;

use App\Models\CallEventLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $eventType,
        public array $data
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.calls'),
            new PrivateChannel('tenant.'.$this->tenantId.'.calls.'.$this->eventType),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.'.$this->eventType;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => $this->eventType,
            'schema_version' => CallEventLog::SCHEMA_VERSION,
            'data' => $this->data,
        ];
    }
}
