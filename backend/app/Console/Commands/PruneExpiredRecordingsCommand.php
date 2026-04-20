<?php

namespace App\Console\Commands;

use App\Models\Recording;
use App\Models\Organization;
use App\Services\Storage\StorageDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneExpiredRecordingsCommand extends Command
{
    public function __construct(
        protected ?StorageDriver $storageDriver = null,
    ) {
        parent::__construct();
    }

    protected $signature = 'nizam:prune-recordings
                            {--dry-run : List expired recordings without deleting them}
                            {--organization= : Restrict pruning to a specific organization UUID}';

    protected $description = 'Delete recordings that have exceeded their organization retention period';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $organizationId = $this->option('organization');

        $query = Organization::whereNotNull('recording_retention_days')
            ->where('recording_retention_days', '>', 0);

        if ($organizationId) {
            $query->where('id', $organizationId);
        }

        $organizations = $query->get();

        if ($organizations->isEmpty()) {
            $this->info('No organizations with a recording_retention_days policy found.');

            return self::SUCCESS;
        }

        $totalDeleted = 0;
        $totalFailed = 0;

        foreach ($organizations as $organization) {
            [$deleted, $failed] = $this->pruneForOrganization($organization, $dryRun);
            $totalDeleted += $deleted;
            $totalFailed += $failed;
        }

        $verb = $dryRun ? 'would be deleted' : 'deleted';
        $this->info("Pruning complete. {$totalDeleted} recording(s) {$verb}, {$totalFailed} failed.");

        if ($totalFailed > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Prune recordings for a single organization.
     *
     * @return array{0: int, 1: int} [deleted, failed]
     */
    protected function pruneForOrganization(Organization $organization, bool $dryRun): array
    {
        $cutoff = now()->subDays($organization->recording_retention_days);

        $recordings = Recording::where('organization_id', $organization->id)
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($recordings->isEmpty()) {
            return [0, 0];
        }

        $deleted = 0;
        $failed = 0;

        foreach ($recordings as $recording) {
            if ($dryRun) {
                $this->line("  [dry-run] Would delete recording {$recording->id} (organization={$organization->slug}, file={$recording->file_path})");
                $deleted++;

                continue;
            }

            try {
                if ($recording->file_path) {
                    if ($recording->storage_driver === 'local') {
                        $this->storageDriver()->delete($recording->file_path);
                    } elseif (Storage::disk('recordings')->exists($recording->file_path)) {
                        Storage::disk('recordings')->delete($recording->file_path);
                    }
                }

                $recording->delete();
                $deleted++;

                Log::info('nizam:prune-recordings: deleted recording', [
                    'recording_id' => $recording->id,
                    'organization_id' => $organization->id,
                    'organization_slug' => $organization->slug,
                    'file_path' => $recording->file_path,
                    'retention_days' => $organization->recording_retention_days,
                ]);
            } catch (\Throwable $e) {
                $failed++;
                Log::error('nizam:prune-recordings: failed to delete recording', [
                    'recording_id' => $recording->id,
                    'organization_id' => $organization->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to delete recording {$recording->id}: {$e->getMessage()}");
            }
        }

        return [$deleted, $failed];
    }

    protected function storageDriver(): StorageDriver
    {
        $this->storageDriver ??= app(StorageDriver::class);

        return $this->storageDriver;
    }
}
