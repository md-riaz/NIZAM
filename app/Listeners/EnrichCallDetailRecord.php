<?php

namespace App\Listeners;

use App\Events\CallDetailRecordCreated;
use App\Jobs\EnrichCdrJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnrichCallDetailRecord implements ShouldQueue
{
    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'cdr-enrichment';

    /**
     * Handle the event.
     */
    public function handle(CallDetailRecordCreated $event): void
    {
        EnrichCdrJob::dispatch($event->cdr);
    }
}
