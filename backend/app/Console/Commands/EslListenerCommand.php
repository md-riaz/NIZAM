<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Call\CallEventIngestionService;
use App\Services\Call\EventNormalizer;
use App\Services\EslConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EslListenerCommand extends Command
{
    protected $signature = 'nizam:esl-listener';

    protected $description = 'Listen to FreeSWITCH ESL and ingest normalized domain events.';

    public function handle(
        EslConnectionManager $esl,
        EventNormalizer $normalizer,
        CallEventIngestionService $ingestion
    ): int {
        $this->info('Starting FreeSWITCH ESL Listener...');

        if (! $esl->connect()) {
            $this->error('Failed to connect to FreeSWITCH ESL.');
            return self::FAILURE;
        }

        $eventsToSubscribe = [
            'CHANNEL_ANSWER',
            'CHANNEL_HANGUP_COMPLETE',
            'DTMF',
            'RECORD_STOP'
        ];

        if (! $esl->subscribeEvents($eventsToSubscribe)) {
            $this->error('Failed to subscribe to ESL events.');
            return self::FAILURE;
        }

        $this->info('Subscribed to events: ' . implode(', ', $eventsToSubscribe));

        while (true) {
            // Read event, timeout every 5 seconds to allow loop to check connection or gracefully exit
            $rawEvent = $esl->readEvent(5);

            if ($rawEvent && isset($rawEvent['Event-Name'])) {
                $normalized = $normalizer->normalize($rawEvent);

                if ($normalized) {
                    $domain = $normalized['domain'];
                    if ($domain) {
                        // Cache tenant lookups to reduce DB hits in this tight loop
                        $tenantId = Cache::remember("tenant_domain_{$domain}", 60, function () use ($domain) {
                            return Tenant::where('domain', $domain)->where('is_active', true)->value('id');
                        });

                        if ($tenantId) {
                            $tenant = new Tenant(['id' => $tenantId]);
                            $tenant->exists = true;

                            $ingestion->ingest(
                                $tenant,
                                $normalized['type'],
                                $normalized['call_uuid'],
                                array_merge($normalized['payload'], ['event_id' => $rawEvent['Event-UUID'] ?? null]),
                                null, // call session can be injected by ingestion service or left null to match later
                                'esl_listener'
                            );

                            $this->info("Ingested [{$normalized['type']}] for call [{$normalized['call_uuid']}]");
                        } else {
                            $this->warn("Dropped event: unknown domain [{$domain}]");
                        }
                    } else {
                        $this->warn("Dropped event: no domain resolved for call [{$normalized['call_uuid']}]");
                    }
                }
            }

            // Connection health check
            if (! $esl->isConnected()) {
                $this->warn('Lost ESL connection. Attempting to reconnect...');
                if (! $esl->reconnect()) {
                    $this->error('Failed to reconnect to FreeSWITCH ESL.');
                    return self::FAILURE;
                }
                $esl->subscribeEvents($eventsToSubscribe);
                $this->info('Reconnected and resubscribed.');
            }
        }

        return self::SUCCESS;
    }
}
