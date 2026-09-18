<?php

namespace App\Console\Commands;

use App\Models\ProcessedCdrFile;
use App\Services\Cdr\XmlCdrSpool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Put quarantined call detail records back into the spool.
 *
 * Quarantine is deliberately terminal: the ingester stops offering a record once
 * it is out of attempts, so one poison file cannot occupy a slot in every batch
 * forever. But the reason a record failed is usually fixable — an organization
 * that did not exist yet, a database that was down, a domain typo — and once it
 * is fixed the operator needs a supported way to say "try these again".
 *
 * Moving the file back by hand is not enough on its own: the ledger still
 * remembers the terminal status, and discovery would skip the file on sight.
 * This moves the file and clears the ledger entry together.
 */
class RequeueXmlCdrCommand extends Command
{
    protected $signature = 'cdr:requeue-xml
                            {--reason= : Only requeue this quarantine reason (xml, sql or size)}
                            {--file=* : Only requeue these file names}
                            {--dry-run : List what would be requeued without moving anything}';

    protected $description = 'Return quarantined XML CDR records to the spool to be ingested again';

    public function handle(XmlCdrSpool $spool): int
    {
        $reasons = $this->reasons();

        if ($reasons === []) {
            $this->error(sprintf('Unknown quarantine reason. Expected one of: %s.', implode(', ', XmlCdrSpool::REASONS)));

            return self::FAILURE;
        }

        $only = array_filter((array) $this->option('file'));
        $dryRun = (bool) $this->option('dry-run');
        $requeued = 0;
        $skipped = 0;

        foreach ($reasons as $reason) {
            $directory = $spool->quarantineDirectory($reason);

            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::files($directory) as $file) {
                $name = $file->getFilename();

                // The name on disk is not necessarily the name in the ledger. A
                // quarantine whose preferred name was taken gets a prefixed one,
                // and the ledger keeps the original. The stored quarantine path
                // is the only thing that ties the two together, so it is what
                // selects the row — with the ledger name accepted as a fallback
                // for entries written before a collision was possible.
                $record = ProcessedCdrFile::query()
                    ->where('quarantine_path', $file->getPathname())
                    ->first()
                    ?? ProcessedCdrFile::query()->where('file_name', $name)->first();

                if ($only !== [] && ! in_array($name, $only, true) && ! in_array((string) $record?->file_name, $only, true)) {
                    continue;
                }

                // Restore the record under the name the ledger knows it by, so
                // discovery and the ledger agree about what is in the spool.
                $spoolName = $record?->file_name ?: $name;
                $destination = rtrim($spool->directory(), '/').'/'.$spoolName;

                if ($dryRun) {
                    $this->line(sprintf('Would requeue %s from %s.', $spoolName, $reason));
                    $requeued++;

                    continue;
                }

                // The ledger entry goes first. If the move then fails, the record
                // is still in quarantine with no entry — a later run picks it up
                // and nothing has been lost. The other order can strand a live
                // file under a terminal entry that discovery skips forever, and
                // no ordering makes a filesystem move and a database write
                // atomic, so the question is only which failure is survivable.
                $record?->delete();

                // A no-replace move: the destination may be live work, and
                // `rename()` would replace it silently.
                if (! $spool->moveWithoutReplacing($file->getPathname(), $destination)) {
                    $this->warn(sprintf('Skipped %s: the spool already holds that record, or the move failed.', $spoolName));
                    $skipped++;

                    continue;
                }

                $this->line(sprintf('Requeued %s from %s.', $spoolName, $reason));
                $requeued++;
            }
        }

        $this->info(sprintf(
            '%s %d record(s)%s.',
            $dryRun ? 'Would requeue' : 'Requeued',
            $requeued,
            $skipped > 0 ? sprintf(', skipped %d', $skipped) : ''
        ));

        return self::SUCCESS;
    }

    /**
     * The quarantine directories this run should read.
     *
     * @return array<int, string>
     */
    protected function reasons(): array
    {
        $reason = $this->option('reason');

        if ($reason === null || $reason === '') {
            return XmlCdrSpool::REASONS;
        }

        return in_array($reason, XmlCdrSpool::REASONS, true) ? [$reason] : [];
    }
}
