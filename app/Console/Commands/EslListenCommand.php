<?php

namespace App\Console\Commands;

use App\Services\EslConnectionManager;
use App\Services\EventProcessor;
use Illuminate\Console\Command;

class EslListenCommand extends Command
{
    protected $signature = 'nizam:esl-listen';
    protected $description = 'Connect to FreeSWITCH ESL and listen for events';

    public function handle(EventProcessor $processor): int
    {
        $this->info('Connecting to FreeSWITCH ESL...');

        $esl = EslConnectionManager::fromConfig();

        if (!$esl->connect()) {
            $this->error('Failed to connect to FreeSWITCH ESL.');
            return self::FAILURE;
        }

        $this->info('Connected to FreeSWITCH ESL.');

        // Subscribe to relevant events
        $events = [
            'CHANNEL_CREATE',
            'CHANNEL_ANSWER',
            'CHANNEL_HANGUP_COMPLETE',
            'CUSTOM',
        ];

        if (!$esl->subscribeEvents($events)) {
            $this->error('Failed to subscribe to events.');
            return self::FAILURE;
        }

        $this->info('Subscribed to events: ' . implode(', ', $events));
        $this->info('Listening for events... (Press Ctrl+C to stop)');

        // Event loop
        while (true) {
            $event = $esl->readEvent(timeoutSeconds: 1);

            if ($event !== null && isset($event['Event-Name'])) {
                $processor->process($event);
            }

            // Allow graceful shutdown
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        return self::SUCCESS;
    }
}
