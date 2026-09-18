<?php

namespace App\Services\Cdr;

use Illuminate\Support\Facades\File;

/**
 * The on-disk spool mod_xml_cdr writes into, and the quarantine beneath it.
 *
 * The file is the queue and the database row is the acknowledgement: nothing is
 * deleted until the record is committed, and anything that cannot be committed
 * is moved out of the working directory rather than left in place. That is the
 * property that makes the spool survive a crash, a database outage or a restart
 * — FreeSWITCH will not send a record twice, so the only durable copy is the one
 * on disk.
 *
 * Leaving failures in the working directory is what makes a spool degrade: every
 * later pass re-reads and re-hashes them, and a single poison record can bury the
 * scan. FusionPBX and FS PBX both quarantine by reason, which keeps the scan
 * proportional to real work and leaves an inspectable pile to requeue.
 */
class XmlCdrSpool
{
    /** Unreadable, truncated, or not parseable as XML. */
    public const REASON_XML = 'xml';

    /** Zero bytes, or larger than a call detail record has any reason to be. */
    public const REASON_SIZE = 'size';

    /** Parsed, but could not be written — unknown domain, database error. */
    public const REASON_SQL = 'sql';

    public const REASONS = [self::REASON_XML, self::REASON_SIZE, self::REASON_SQL];

    public function __construct(
        protected ?string $directory = null,
    ) {
        $this->directory ??= (string) config('telephony.xml_cdr.directory');
    }

    public function directory(): string
    {
        return (string) $this->directory;
    }

    /**
     * Where a record quarantined for the given reason goes.
     */
    public function quarantineDirectory(string $reason): string
    {
        $reason = in_array($reason, self::REASONS, true) ? $reason : self::REASON_SQL;

        return rtrim($this->directory(), '/').'/failed/'.$reason;
    }

    /**
     * Create the quarantine tree so a move never fails for want of a directory.
     */
    public function ensureQuarantineDirectories(): void
    {
        foreach (self::REASONS as $reason) {
            $path = $this->quarantineDirectory($reason);

            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0770, true, true);
            }
        }
    }

    /**
     * Move a record out of the working directory.
     *
     * Returns the destination, or null when the file is already gone — a move
     * that loses a race with another worker is not an error worth raising.
     */
    public function quarantine(string $path, string $reason): ?string
    {
        if (! File::exists($path)) {
            return null;
        }

        $this->ensureQuarantineDirectories();

        $destination = $this->quarantineDirectory($reason).'/'.basename($path);

        // A record quarantined twice must not overwrite the first copy, which
        // would discard the evidence the quarantine exists to keep.
        if (File::exists($destination)) {
            $destination = $this->quarantineDirectory($reason).'/'.now()->format('YmdHis').'-'.basename($path);
        }

        return @rename($path, $destination) ? $destination : null;
    }
}
