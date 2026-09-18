<?php

namespace App\Services\Cdr;

use App\Models\ProcessedCdrFile;
use Illuminate\Support\Facades\File;
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
 * - A record that failed before is retried up to a bounded number of attempts,
 *   then quarantined. A transient database outage must not cost a call record,
 *   and a genuinely bad record must not be retried forever.
 *
 * The batch limit bounds a single pass. After a backlog — the watcher was down,
 * or an inotify queue overflowed — the directory can hold tens of thousands of
 * files, and reading all of them in one pass would block the loop for minutes.
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

        $batchLimit = $this->batchLimit();
        $maxBytes = $this->maxBytes();
        $pending = [];

        foreach ($this->candidates() as $file) {
            if (count($pending) >= $batchLimit) {
                break;
            }

            $path = $file->getPathname();
            $size = $file->getSize();

            // Judge size before reading: a zero-byte or oversized record is not
            // going to become valid on a later pass.
            if ($size === 0 || $size >= $maxBytes) {
                $this->spool->quarantine($path, XmlCdrSpool::REASON_SIZE);

                continue;
            }

            // mod_xml_cdr creates the file before writing it, so a reader can
            // arrive mid-write. Leave it for the next pass rather than read it.
            if (! $this->spool->isSettled($path, $this->stabilityMicroseconds())) {
                continue;
            }

            if (! $this->shouldAttempt($path)) {
                continue;
            }

            $pending[] = $path;
        }

        return $pending;
    }

    /**
     * Spool files in a stable order, ignoring the quarantine tree.
     *
     * The scan is deliberately non-recursive: `failed/` holds records this
     * service has already given up on, and walking back into it would undo the
     * quarantine.
     *
     * @return array<int, SplFileInfo>
     */
    protected function candidates(): array
    {
        $files = collect(File::files($this->directory))
            ->filter(fn (SplFileInfo $file) => str_ends_with(strtolower($file->getFilename()), '.xml'))
            ->sortBy(fn (SplFileInfo $file) => $file->getFilename())
            ->values()
            ->all();

        return $files;
    }

    /**
     * Whether this pass should read the file, given what the ledger remembers.
     *
     * A record already committed is skipped. A record that failed is retried
     * until the attempt budget runs out, and is then moved out of the spool so it
     * stops costing a stat and a query on every later pass.
     */
    protected function shouldAttempt(string $path): bool
    {
        $record = ProcessedCdrFile::query()
            ->where('dedupe_key', ProcessedCdrFile::dedupeKeyFor($path, $this->checksumFor($path)))
            ->first();

        if (! $record) {
            return true;
        }

        if (in_array($record->status, [ProcessedCdrFile::STATUS_PROCESSED, ProcessedCdrFile::STATUS_QUARANTINED], true)) {
            return false;
        }

        if ($record->attempts >= $this->maxAttempts()) {
            $destination = $this->spool->quarantine($path, XmlCdrSpool::REASON_SQL);

            $record->forceFill([
                'status' => ProcessedCdrFile::STATUS_QUARANTINED,
                'quarantine_reason' => XmlCdrSpool::REASON_SQL,
                'quarantine_path' => $destination,
            ])->save();

            return false;
        }

        // Space the retries out. Attempting three times inside one pass is not a
        // retry — the database is still down, the organization still does not
        // exist — it just burns the budget in milliseconds. Waiting also keeps a
        // failing record from occupying a slot in every batch, which would hide
        // everything queued behind it.
        return ! $record->last_attempted_at
            || ! $record->last_attempted_at->addSeconds($this->retryDelaySeconds())->isFuture();
    }

    protected function checksumFor(string $path): ?string
    {
        $checksum = @hash_file('sha256', $path);

        return $checksum === false ? null : $checksum;
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
}
