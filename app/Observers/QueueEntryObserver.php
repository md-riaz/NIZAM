<?php

namespace App\Observers;

use App\Models\QueueEntry;
use App\Services\WallboardProjectionService;

class QueueEntryObserver
{
    public function created(QueueEntry $queueEntry): void
    {
        app(WallboardProjectionService::class)->refreshQueueProjection($queueEntry->queue_id);
    }

    public function updated(QueueEntry $queueEntry): void
    {
        app(WallboardProjectionService::class)->refreshQueueProjection($queueEntry->queue_id);
    }

    public function deleted(QueueEntry $queueEntry): void
    {
        app(WallboardProjectionService::class)->refreshQueueProjection($queueEntry->queue_id);
    }
}
