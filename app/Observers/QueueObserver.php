<?php

namespace App\Observers;

use App\Models\Queue;
use App\Services\WallboardProjectionService;

class QueueObserver
{
    public function created(Queue $queue): void
    {
        app(WallboardProjectionService::class)->refreshQueueProjection($queue);
    }

    public function updated(Queue $queue): void
    {
        $service = app(WallboardProjectionService::class);

        if ($queue->is_active) {
            $service->refreshQueueProjection($queue);

            return;
        }

        $service->deleteQueueProjection($queue->id);
    }

    public function deleted(Queue $queue): void
    {
        app(WallboardProjectionService::class)->deleteQueueProjection($queue->id);
    }
}
