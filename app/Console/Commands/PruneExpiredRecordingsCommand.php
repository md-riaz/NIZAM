<?php

namespace App\Console\Commands;

use App\Models\Recording;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneExpiredRecordingsCommand extends Command
{
    protected $signature = 'nizam:prune-recordings
                            {--dry-run : List expired recordings without deleting them}
                            {--tenant= : Restrict pruning to a specific tenant UUID}';

    protected $description = 'Delete recordings that have exceeded their tenant retention period';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $query = Tenant::whereNotNull('recording_retention_days')
            ->where('recording_retention_days', '>', 0);

        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants with a recording_retention_days policy found.');

            return self::SUCCESS;
        }

        $totalDeleted = 0;
        $totalFailed = 0;

        foreach ($tenants as $tenant) {
            [$deleted, $failed] = $this->pruneForTenant($tenant, $dryRun);
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
     * Prune recordings for a single tenant.
     *
     * @return array{0: int, 1: int} [deleted, failed]
     */
    protected function pruneForTenant(Tenant $tenant, bool $dryRun): array
    {
        $cutoff = now()->subDays($tenant->recording_retention_days);

        $recordings = Recording::where('tenant_id', $tenant->id)
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($recordings->isEmpty()) {
            return [0, 0];
        }

        $deleted = 0;
        $failed = 0;

        foreach ($recordings as $recording) {
            if ($dryRun) {
                $this->line("  [dry-run] Would delete recording {$recording->id} (tenant={$tenant->slug}, file={$recording->file_path})");
                $deleted++;

                continue;
            }

            try {
                // Remove the file from storage if it exists
                if ($recording->file_path && Storage::exists($recording->file_path)) {
                    Storage::delete($recording->file_path);
                }

                $recording->delete();
                $deleted++;

                Log::info('nizam:prune-recordings: deleted recording', [
                    'recording_id' => $recording->id,
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'file_path' => $recording->file_path,
                    'retention_days' => $tenant->recording_retention_days,
                ]);
            } catch (\Throwable $e) {
                $failed++;
                Log::error('nizam:prune-recordings: failed to delete recording', [
                    'recording_id' => $recording->id,
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to delete recording {$recording->id}: {$e->getMessage()}");
            }
        }

        return [$deleted, $failed];
    }
}
