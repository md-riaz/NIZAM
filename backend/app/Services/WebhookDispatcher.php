<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;

class WebhookDispatcher
{
    /**
     * Dispatch webhooks for a given organization and event.
     */
    public function dispatch(string $organizationId, string $eventType, array $payload): void
    {
        $webhooks = Webhook::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get()
            ->filter(function (Webhook $webhook) use ($eventType) {
                return in_array($eventType, $webhook->events ?? []);
            });

        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch($webhook, $eventType, $payload);
        }
    }
}
