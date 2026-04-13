<?php

namespace App\Console\Commands;

use App\Services\Cdr\XmlCdrDiscoveryService;
use App\Services\Cdr\XmlCdrIngestionService;
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

        $this->registerSignalHandlers();

        if ($once) {
            $processed = $this->processPendingFiles();
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
        $processed = $this->processPendingFiles();
        $watch = inotify_init();

        if ($watch === false) {
            $this->warn('Unable to initialize inotify. Falling back to polling XML CDR watcher.');

            return $this->runPollingLoop();
        }

        stream_set_blocking($watch, false);
        $mask = IN_CLOSE_WRITE | IN_MOVED_TO | IN_CREATE;
        $watchDescriptor = inotify_add_watch($watch, $directory, $mask);

        if ($watchDescriptor === false) {
            fclose($watch);
            $this->warn('Unable to watch XML CDR directory with inotify. Falling back to polling XML CDR watcher.');

            return $this->runPollingLoop();
        }

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

                if ($events !== []) {
                    $processed += $this->processPendingFiles();
                }
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
            $processed += $this->processPendingFiles();

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

    protected function processPendingFiles(): int
    {
        $processed = 0;

        foreach ($this->discovery()->pendingFiles() as $path) {
            try {
                $record = $this->ingestion()->ingest($path);
                $processed++;

                $this->line(sprintf('Ingested %s [%s].', $record->file_name, $record->call_uuid ?? 'unknown'));
            } catch (\Throwable $exception) {
                $this->ingestion()->markFailed($path, $exception);

                $message = sprintf('Failed to ingest %s: %s', basename($path), $exception->getMessage());
                $this->error($message);

                Log::error('cdr:ingest-xml failed', [
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $processed;
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
