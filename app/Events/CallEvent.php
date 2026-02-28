<?php

namespace App\Events;

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
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.'.$this->eventType;
    }
}
