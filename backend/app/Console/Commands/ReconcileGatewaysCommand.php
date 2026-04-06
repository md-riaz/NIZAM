<?php

namespace App\Console\Commands;

use App\Models\Gateway;
use App\Services\Media\GatewayProvisioningService;
use Illuminate\Console\Command;

class ReconcileGatewaysCommand extends Command
{
    protected $signature = 'nizam:reconcile-gateways {--dry-run : Preview changes without writing files or reloading FreeSWITCH}';

    protected $description = 'Reconcile generated FreeSWITCH gateway XML with gateway rows in the database';

    public function handle(GatewayProvisioningService $provisioning): int
    {
        $gateways = Gateway::query()->get();

        if ($this->option('dry-run')) {
            $summary = $provisioning->plan($gateways);
            $this->line('Gateway reconcile plan:');
            $this->table(['create_or_update', 'remove_orphans'], [[
                implode("\n", $summary['create_or_update']) ?: '-',
                implode("\n", $summary['remove_orphans']) ?: '-',
            ]]);

            return self::SUCCESS;
        }

        $summary = $provisioning->reconcile($gateways);

        $this->info('Gateway XML reconciled.');
        $this->table(['create_or_update', 'remove_orphans'], [[
            implode("\n", $summary['create_or_update']) ?: '-',
            implode("\n", $summary['remove_orphans']) ?: '-',
        ]]);

        return self::SUCCESS;
    }
}
