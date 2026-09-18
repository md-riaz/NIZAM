<?php

namespace App\Console\Commands;

use App\Services\Cdr\XmlCdrDiscoveryService;
use App\Services\Cdr\XmlCdrIngestionService;
use App\Services\Cdr\XmlCdrSpool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IngestXmlCdrCommand extends Command
{
    protected $signature = 'cdr:ingest-xml
                            {--once : Process pending files once and exit}
                            {--poll-interval= : Override polling interval in seconds}';

    protected $description = 'Ingest XML CDR files using inotify when available and polling otherwise';

    protected bool $shouldRun = true;

    public function __construct(
        protected ?XmlCdrDiscoveryService $discovery = null,
        protected ?XmlCdrIngestionService $ingestion = null,
        protected ?XmlCdrSpool $spool = null,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('telephony.xml_cdr.enabled')) {
            $this->warn('XML CDR ingestion is disabled.');

            return self::SUCCESS;
        }

        $directory = (string) config('telephony.xml_cdr.directory');

        if ($directory === '' || ! is_dir($directory)) {
            $this->error(sprintf('XML CDR directory is not available: %s', $directory));

            return self::FAILURE;
        }

        $watcher = (string) config('telephony.xml_cdr.watcher', 'inotify');
        $once = (bool) $this->option('once');

        // Create the quarantine tree up front so a move never fails for want of
        // a directory at the moment something has already gone wrong.
        $this->spool()->ensureQuarantineDirectories();

        $this->registerSignalHandlers();

        if ($once) {
            $processed = $this->drainPendingFiles();
            $this->info(sprintf('Processed %d XML CDR file(s).', $processed));

            return self::SUCCESS;
        }

        if ($watcher === 'inotify' && $this->supportsInotify()) {
            $this->info('Using inotify XML CDR watcher.');

            return $this->runInotifyLoop($directory);
        }

        $this->warn('Falling back to polling XML CDR watcher.');

        return $this->runPollingLoop();
    }

    protected function runInotifyLoop(string $directory): int
    {
        // Sweep before watching. Anything written while this process was down
        // produced an event nobody was listening for, and no later event will
        // mention it — so the backlog is drained in full, not one batch of it.
        $processed = $this->drainPendingFiles();
        $watch = inotify_init();

        if ($watch === false) {
            $this->warn('Unable to initialize inotify. Falling back to polling XML CDR watcher.');

            return $this->runPollingLoop();
        }

        stream_set_blocking($watch, false);

        // IN_CREATE fires before the record has been written, so watching it
        // guarantees reading files mid-write. Close and move are the two events
        // that mean a complete file is present.
        $mask = IN_CLOSE_WRITE | IN_MOVED_TO;
        $watchDescriptor = inotify_add_watch($watch, $directory, $mask);

        if ($watchDescriptor === false) {
            fclose($watch);
            $this->warn('Unable to watch XML CDR directory with inotify. Falling back to polling XML CDR watcher.');

            return $this->runPollingLoop();
        }

        $lastSweep = time();
        $sweepInterval = $this->sweepIntervalSeconds();

        while ($this->shouldRun) {
            $read = [$watch];
            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 1);

            if ($changed === false) {
                break;
            }

            if ($changed > 0) {
                $events = inotify_read($watch) ?: [];

                foreach ($events as $event) {
                    // The kernel drops events once its queue is full, and says so
                    // exactly once. Without handling this, every record spooled
                    // during a burst is invisible until some later event happens
                    // to arrive — on a quiet system, potentially never.
                    if (($event['mask'] ?? 0) & IN_Q_OVERFLOW) {
                        $this->warn('inotify queue overflowed; sweeping the whole spool.');
                        Log::warning('cdr:ingest-xml inotify queue overflow', ['directory' => $directory]);

                        inotify_rm_watch($watch, $watchDescriptor);
                        $watchDescriptor = inotify_add_watch($watch, $directory, $mask);

                        // Without a watch there are no more events, and the
                        // periodic sweep alone would leave records sitting for
                        // minutes. Polling is slower than inotify but it cannot
                        // stop working, so it is the right thing to fall back to.
                        if ($watchDescriptor === false) {
                            fclose($watch);
                            $this->warn('Could not re-arm the XML CDR watch. Falling back to polling.');
                            Log::warning('cdr:ingest-xml could not re-arm inotify watch', ['directory' => $directory]);

                            return $this->runPollingLoop();
                        }

                        break;
                    }
                }

                if ($events !== []) {
                    $processed += $this->drainPendingFiles();
                    $lastSweep = time();
                }
            }

            // Events are a hint, never the record of what is owed. A periodic
            // sweep is what makes a missed, coalesced or dropped event cost
            // minutes of latency instead of a lost call record.
            if (time() - $lastSweep >= $sweepInterval) {
                $processed += $this->drainPendingFiles();
                $lastSweep = time();
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        inotify_rm_watch($watch, $watchDescriptor);
        fclose($watch);

        $this->info(sprintf('XML CDR watcher stopped after processing %d file(s).', $processed));

        return self::SUCCESS;
    }

    protected function runPollingLoop(): int
    {
        $processed = 0;
        $interval = $this->pollIntervalSeconds();

        while ($this->shouldRun) {
            $processed += $this->drainPendingFiles();

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if (! $this->shouldRun) {
                break;
            }

            sleep($interval);
        }

        $this->info(sprintf('XML CDR watcher stopped after processing %d file(s).', $processed));

        return self::SUCCESS;
    }

    /**
     * Work the spool down in batches until a pass finds nothing left.
     *
     * Discovery returns at most one batch so a single pass cannot block the loop
     * for minutes after a backlog. Draining here keeps that bound while still
     * clearing the whole spool before going back to waiting.
     */
    protected function drainPendingFiles(): int
    {
        $processed = 0;

        while ($this->shouldRun) {
            $batch = $this->processPendingFiles();

            if ($batch === 0) {
                break;
            }

            $processed += $batch;

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        return $processed;
    }

    /**
     * Attempt one batch, returning how many files were tried.
     *
     * The count is attempts rather than successes on purpose: the drain loop uses
     * it to decide whether the spool still holds work, and a batch of records
     * that all fail is still progress — each one burns an attempt and is
     * quarantined once its budget runs out. Counting only successes would let a
     * batch of poison records hide everything queued behind them.
     */
    protected function processPendingFiles(): int
    {
        $attempted = 0;
        $ingested = 0;

        foreach ($this->discovery()->pendingFiles() as $path) {
            $attempted++;

            try {
                $record = $this->ingestion()->ingest($path);
                $ingested++;

                $this->line(sprintf('Ingested %s [%s].', $record->file_name, $record->call_uuid ?? 'unknown'));
            } catch (\Throwable $exception) {
                $record = $this->ingestion()->markFailed($path, $exception);

                $message = sprintf('Failed to ingest %s: %s', basename($path), $exception->getMessage());
                $this->error($message);

                Log::error('cdr:ingest-xml failed', [
                    'path' => $path,
                    'attempts' => $record->attempts,
                    'status' => $record->status,
                    'quarantine_path' => $record->quarantine_path,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($attempted > 0) {
            $this->line(sprintf('Batch complete: %d of %d ingested.', $ingested, $attempted));
        }

        return $attempted;
    }

    protected function discovery(): XmlCdrDiscoveryService
    {
        $this->discovery ??= app(XmlCdrDiscoveryService::class);

        return $this->discovery;
    }

    protected function ingestion(): XmlCdrIngestionService
    {
        $this->ingestion ??= app(XmlCdrIngestionService::class);

        return $this->ingestion;
    }

    protected function supportsInotify(): bool
    {
        return extension_loaded('inotify')
            && function_exists('inotify_init')
            && function_exists('inotify_add_watch')
            && function_exists('inotify_read');
    }

    protected function pollIntervalSeconds(): int
    {
        $configured = $this->option('poll-interval');

        if ($configured !== null) {
            return max(1, (int) $configured);
        }

        return max(1, (int) config('telephony.xml_cdr.poll_interval_seconds', 5));
    }

    /**
     * How often the inotify loop sweeps regardless of events.
     */
    protected function sweepIntervalSeconds(): int
    {
        return max(1, (int) config('telephony.xml_cdr.sweep_interval_seconds', 300));
    }

    protected function spool(): XmlCdrSpool
    {
        return $this->spool ??= app(XmlCdrSpool::class);
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
