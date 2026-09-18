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

                if ($only !== [] && ! in_array($name, $only, true)) {
                    continue;
                }

                $destination = rtrim($spool->directory(), '/').'/'.$name;

                // Never overwrite live work: a record already back in the spool
                // is being dealt with, and clobbering it would lose whichever
                // copy is further along.
                if (File::exists($destination)) {
                    $this->warn(sprintf('Skipped %s: already present in the spool.', $name));
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf('Would requeue %s from %s.', $name, $reason));
                    $requeued++;

                    continue;
                }

                if (! @rename($file->getPathname(), $destination)) {
                    $this->error(sprintf('Could not move %s back into the spool.', $name));
                    $skipped++;

                    continue;
                }

                // The ledger entry goes with it. Leaving it would have discovery
                // skip the file it just put back.
                ProcessedCdrFile::query()->where('file_name', $name)->delete();

                $this->line(sprintf('Requeued %s from %s.', $name, $reason));
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
