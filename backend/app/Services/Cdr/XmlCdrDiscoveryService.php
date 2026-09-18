<?php

namespace App\Services\Cdr;

use App\Models\ProcessedCdrFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use SplFileInfo;

/**
 * Chooses which spooled records are worth reading on this pass.
 *
 * Three things decide that, and each one exists because of a way a spool goes
 * wrong in production:
 *
 * - A record still being written is skipped until its size stops moving, so a
 *   half-flushed file is never parsed as a whole one.
 * - A record that is empty or implausibly large is quarantined without being
 *   read, so one malformed write cannot exhaust memory on every pass.
 * - A record that failed before is retried, spaced out, up to a bounded number
 *   of attempts and then quarantined. A transient database outage must not cost
 *   a call record, and a bad record must not be retried forever.
 *
 * The pass is built to stay cheap as the spool grows, because it runs on every
 * filesystem event:
 *
 * - The batch is chosen from file metadata alone. Nothing is read and nothing is
 *   hashed — the ledger is keyed on the file name, which mod_xml_cdr has already
 *   made unique per call.
 * - The ledger is consulted once for the whole batch rather than once per file.
 * - The settling pause is taken once for the whole batch rather than once per
 *   file, so a batch of a hundred costs one pause instead of a hundred.
 */
class XmlCdrDiscoveryService
{
    public function __construct(
        protected ?string $directory = null,
        protected ?XmlCdrSpool $spool = null,
    ) {
        $this->directory ??= config('telephony.xml_cdr.directory');
        $this->spool ??= new XmlCdrSpool($this->directory);
    }

    /**
     * Paths to attempt on this pass, oldest first.
     *
     * @return array<int, string>
     */
    public function pendingFiles(): array
    {
        if (! $this->directory || ! File::isDirectory($this->directory)) {
            return [];
        }

        $candidates = $this->sizedCandidates();

        if ($candidates === []) {
            return [];
        }

        $settled = $this->rejectUnsettled($candidates);

        if ($settled === []) {
            return [];
        }

        return $this->rejectSettledLedgerEntries($settled);
    }

    /**
     * Spool files worth considering, with the size each one had when scanned.
     *
     * Empty and implausibly large records are moved aside here rather than
     * offered: neither becomes valid on a later pass, and reading one costs
     * either a parse failure or a great deal of memory.
     *
     * The scan is deliberately non-recursive. `failed/` holds records already
     * given up on, and descending into it would undo the quarantine.
     *
     * @return array<string, int> path => size
     */
    protected function sizedCandidates(): array
    {
        $batchLimit = $this->batchLimit();
        $maxBytes = $this->maxBytes();
        $emptyAfter = now()->subSeconds($this->emptyGraceSeconds())->getTimestamp();
        $sized = [];

        $files = collect(File::files($this->directory))
            ->filter(fn (SplFileInfo $file) => str_ends_with(strtolower($file->getFilename()), '.xml'))
            ->sortBy(fn (SplFileInfo $file) => $file->getFilename());

        foreach ($files as $file) {
            if (count($sized) >= $batchLimit) {
                break;
            }

            $size = $file->getSize();

            if ($size >= $maxBytes) {
                $this->spool->quarantine($file->getPathname(), XmlCdrSpool::REASON_SIZE);

                continue;
            }

            if ($size === 0) {
                // Zero bytes is the normal state between mod_xml_cdr creating a
                // record and writing it. Quarantining on sight would move the
                // file out from under an open descriptor: FreeSWITCH would go on
                // writing to the moved inode, and the finished record would sit
                // in a directory the scan deliberately never reads. Only a file
                // that has been empty for a while is really empty.
                if ($file->getMTime() < $emptyAfter) {
                    $this->spool->quarantine($file->getPathname(), XmlCdrSpool::REASON_SIZE);
                }

                continue;
            }

            $sized[$file->getPathname()] = $size;
        }

        return $sized;
    }

    /**
     * Drop the records whose size moved while the batch was being examined.
     *
     * mod_xml_cdr creates the file and then writes it, so a reader can arrive
     * between the two. One pause covers the whole batch: the sizes were taken
     * before it and are compared after, which is the same guarantee as pausing
     * per file at a hundredth of the cost.
     *
     * @param  array<string, int>  $candidates  path => size
     * @return array<int, string>
     */
    protected function rejectUnsettled(array $candidates): array
    {
        $this->pause();

        $settled = [];

        foreach ($candidates as $path => $size) {
            clearstatcache(true, $path);

            if (@filesize($path) === $size) {
                $settled[] = $path;
            }
        }

        return $settled;
    }

    /**
     * Wait long enough for an in-progress write to show up as a size change.
     */
    protected function pause(): void
    {
        usleep($this->stabilityMicroseconds());
    }

    /**
     * Stop offering a record, but only once it is genuinely out of the spool.
     *
     * A move can fail — permissions, a full disk, a name that is already taken.
     * Recording the terminal status anyway would strand the record twice over:
     * the file stays in the working directory where nothing reads it, and the
     * ledger says never to look at it again. A file that has already gone is a
     * different matter, and settling it is correct.
     */
    protected function giveUpOn(ProcessedCdrFile $record, string $path, string $reason): void
    {
        $destination = $this->spool->quarantine($path, $reason);

        if ($destination === null && File::exists($path)) {
            Log::error('cdr:ingest-xml could not quarantine a spooled record', [
                'path' => $path,
                'reason' => $reason,
            ]);

            return;
        }

        $record->forceFill([
            'status' => ProcessedCdrFile::STATUS_QUARANTINED,
            'quarantine_reason' => $reason,
            'quarantine_path' => $destination,
        ])->save();
    }

    /**
     * Drop the records the ledger says are finished with, or not yet due.
     *
     * One query covers the batch. Quarantining here catches a record left over
     * its attempt budget, which the ingester normally handles as it exhausts it.
     *
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    protected function rejectSettledLedgerEntries(array $paths): array
    {
        $ledger = ProcessedCdrFile::query()
            ->whereIn('file_name', array_map('basename', $paths))
            ->get()
            ->keyBy('file_name');

        $maxAttempts = $this->maxAttempts();
        $retryDelay = $this->retryDelaySeconds();
        $pending = [];

        foreach ($paths as $path) {
            $record = $ledger->get(basename($path));

            if (! $record) {
                $pending[] = $path;

                continue;
            }

            if (in_array($record->status, ProcessedCdrFile::settledStatuses(), true)) {
                continue;
            }

            if ($record->attempts >= $maxAttempts) {
                $this->giveUpOn($record, $path, XmlCdrSpool::REASON_SQL);

                continue;
            }

            // Space the retries out. Attempting three times inside one pass is
            // not a retry — whatever failed has had no time to change — and it
            // would keep a failing record in every batch, hiding the work queued
            // behind it.
            $due = ! $record->last_attempted_at
                || ! $record->last_attempted_at->addSeconds($retryDelay)->isFuture();

            if ($due) {
                $pending[] = $path;
            }
        }

        return $pending;
    }

    protected function batchLimit(): int
    {
        return max(1, (int) config('telephony.xml_cdr.batch_limit', 100));
    }

    protected function maxAttempts(): int
    {
        return max(1, (int) config('telephony.xml_cdr.max_attempts', 3));
    }

    protected function retryDelaySeconds(): int
    {
        return max(0, (int) config('telephony.xml_cdr.retry_delay_seconds', 60));
    }

    protected function maxBytes(): int
    {
        return max(1, (int) config('telephony.xml_cdr.max_bytes', 3 * 1024 * 1024));
    }

    protected function stabilityMicroseconds(): int
    {
        return max(0, (int) config('telephony.xml_cdr.stability_microseconds', 10000));
    }

    protected function emptyGraceSeconds(): int
    {
        return max(1, (int) config('telephony.xml_cdr.empty_grace_seconds', 30));
    }
}
