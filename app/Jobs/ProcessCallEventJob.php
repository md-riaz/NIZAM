<?php

namespace App\Jobs;

use App\Models\CallEventLog;
use App\Services\Call\CallEventProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessCallEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $callEventId,
    ) {}

    public function handle(CallEventProcessor $processor): void
    {
        $event = CallEventLog::find($this->callEventId);

        if (! $event) {
            return;
        }

        $processor->process($event);
    }
}
