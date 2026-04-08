<?php

namespace App\Listeners;

use App\Events\CallDeliveryPushRequested;
use App\Jobs\DispatchCallDeliveryPush;
use App\Models\PushNotificationLog;

class HandleCallDeliveryPushRequested
{
    /**
     * Handle the event by dispatching a queued push delivery job.
     *
     * The listener is intentionally synchronous so that the job is dispatched
     * immediately. The actual push delivery is asynchronous — handled inside
     * DispatchCallDeliveryPush — ensuring the ESL/dialplan event path is never
     * blocked by a slow push provider.
     */
    public function handle(CallDeliveryPushRequested $event): void
    {
        $log = PushNotificationLog::query()
            ->where('call_session_id', $event->callSessionId)
            ->where('endpoint_binding_id', $event->endpointBindingId)
            ->whereIn('status', ['queued', 'retrying'])
            ->latest('created_at')
            ->first();

        if (! $log instanceof PushNotificationLog) {
            return;
        }

        DispatchCallDeliveryPush::dispatch(
            $log->id,
            $event->callSessionId,
            $event->endpointBindingId,
            $event->payload,
        );
    }
}
