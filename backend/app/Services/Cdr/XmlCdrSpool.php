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

        $directory = $this->quarantineDirectory($reason);
        $name = basename($path);

        // A record quarantined twice must not overwrite the first copy — that
        // copy is the evidence the quarantine exists to keep. Each attempt uses
        // a no-replace move, so a name already taken is reported rather than
        // silently clobbered, and the next candidate name is tried.
        foreach ($this->candidateNames($name) as $candidate) {
            if ($this->moveWithoutReplacing($path, $directory.'/'.$candidate)) {
                return $directory.'/'.$candidate;
            }
        }

        return null;
    }

    /**
     * Move a file, refusing rather than replacing anything already there.
     *
     * `rename()` replaces its destination silently, and everything this class
     * moves is either live work or the record of an earlier failure — both worth
     * more than the convenience. `link()` fails when the destination exists, so
     * linking and then unlinking the source gives a move that cannot clobber.
     *
     * `link()` also fails across filesystems, which is not a collision. When the
     * destination is genuinely absent the fallback rename is safe: nothing is
     * there to lose.
     */
    public function moveWithoutReplacing(string $from, string $to): bool
    {
        if (@link($from, $to)) {
            @unlink($from);

            return true;
        }

        clearstatcache(true, $to);

        if (file_exists($to)) {
            return false;
        }

        return @rename($from, $to);
    }

    /**
     * Names to try for a quarantined record, in order of preference.
     *
     * A timestamp alone is not enough to separate two collisions: it has
     * one-second resolution, and a burst of failures lands well inside that.
     *
     * @return array<int, string>
     */
    protected function candidateNames(string $name): array
    {
        $names = [$name, now()->format('YmdHis').'-'.$name];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $names[] = now()->format('YmdHis').'-'.uniqid('', true).'-'.$name;
        }

        return $names;
    }
}
