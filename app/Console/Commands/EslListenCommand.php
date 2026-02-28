<?php

namespace App\Console\Commands;

use App\Services\EslConnectionManager;
use App\Services\EventProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EslListenCommand extends Command
{
    protected $signature = 'nizam:esl-listen {--max-retries=0 : Maximum reconnection attempts (0 = unlimited)}';

    protected $description = 'Connect to FreeSWITCH ESL and listen for events with automatic reconnection';

    protected bool $shouldRun = true;

    protected const EVENTS = [
        'CHANNEL_CREATE',
        'CHANNEL_ANSWER',
        'CHANNEL_BRIDGE',
        'CHANNEL_HANGUP_COMPLETE',
        'CUSTOM',
    ];

    public function handle(EventProcessor $processor): int
    {
        $this->registerSignalHandlers();

        $maxRetries = (int) $this->option('max-retries');
        $attempt = 0;

        while ($this->shouldRun) {
            $attempt++;

            if ($maxRetries > 0 && $attempt > $maxRetries) {
                $this->error("Max reconnection attempts ({$maxRetries}) reached. Exiting.");
                Log::error('ESL listener: max reconnection attempts reached', ['attempts' => $maxRetries]);

                return self::FAILURE;
            }

            if ($attempt > 1) {
                $delay = $this->backoffDelay($attempt);
                $this->warn("Reconnecting in {$delay}s (attempt {$attempt})...");
                Log::warning('ESL listener: reconnecting', ['attempt' => $attempt, 'delay' => $delay]);
                sleep($delay);
            }

            if (! $this->shouldRun) {
                break;
            }

            $this->info('Connecting to FreeSWITCH ESL...');

            $esl = EslConnectionManager::fromConfig();

            if (! $esl->connect()) {
                $this->error('Failed to connect to FreeSWITCH ESL.');
                Log::error('ESL listener: connection failed', ['attempt' => $attempt]);

                continue;
            }

            $this->info('Connected to FreeSWITCH ESL.');

            if (! $esl->subscribeEvents(self::EVENTS)) {
                $this->error('Failed to subscribe to events.');
                $esl->disconnect();

                continue;
            }

            $this->info('Subscribed to events: '.implode(', ', self::EVENTS));
            $this->info('Listening for events... (Press Ctrl+C to stop)');

            // Reset attempt counter on successful connection
            $attempt = 0;

            $this->eventLoop($esl, $processor);

            $esl->disconnect();
            $this->warn('ESL connection lost.');
            Log::warning('ESL listener: connection lost, will reconnect');
        }

        $this->info('ESL listener stopped.');

        return self::SUCCESS;
    }

    protected function eventLoop(EslConnectionManager $esl, EventProcessor $processor): void
    {
        $consecutiveErrors = 0;

        while ($this->shouldRun) {
            try {
                $event = $esl->readEvent(timeoutSeconds: 1);

                if ($event !== null && isset($event['Event-Name'])) {
                    $processor->process($event);
                    $consecutiveErrors = 0;
                }

                if (! $esl->isConnected()) {
                    return;
                }
            } catch (\Throwable $e) {
                $consecutiveErrors++;
                Log::error('ESL listener: event processing error', [
                    'error' => $e->getMessage(),
                    'consecutive_errors' => $consecutiveErrors,
                ]);

                if ($consecutiveErrors >= 10) {
                    $this->error('Too many consecutive errors, reconnecting...');

                    return;
                }
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    protected function backoffDelay(int $attempt): int
    {
        // Exponential backoff: 1s, 2s, 4s, 8s, 16s, max 30s
        return min(30, (int) pow(2, $attempt - 2));
    }

    protected function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->shouldRun = false;
            });
            pcntl_signal(SIGTERM, function () {
                $this->shouldRun = false;
            });
        }
    }
}
