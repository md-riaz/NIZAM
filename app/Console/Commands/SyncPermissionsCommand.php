<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Modules\ModuleRegistry;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'nizam:sync-permissions';

    protected $description = 'Sync all core and module permissions into the database';

    /**
     * Core permissions that always exist.
     *
     * @var array<string, string>
     */
    protected array $corePermissions = [
        'tenants.view' => 'View tenants',
        'tenants.create' => 'Create tenants',
        'tenants.update' => 'Update tenants',
        'tenants.delete' => 'Delete tenants',
        'extensions.view' => 'View extensions',
        'extensions.create' => 'Create extensions',
        'extensions.update' => 'Update extensions',
        'extensions.delete' => 'Delete extensions',
        'dids.view' => 'View DIDs',
        'dids.create' => 'Create DIDs',
        'dids.update' => 'Update DIDs',
        'dids.delete' => 'Delete DIDs',
        'ring_groups.view' => 'View ring groups',
        'ring_groups.create' => 'Create ring groups',
        'ring_groups.update' => 'Update ring groups',
        'ring_groups.delete' => 'Delete ring groups',
        'ivrs.view' => 'View IVRs',
        'ivrs.create' => 'Create IVRs',
        'ivrs.update' => 'Update IVRs',
        'ivrs.delete' => 'Delete IVRs',
        'time_conditions.view' => 'View time conditions',
        'time_conditions.create' => 'Create time conditions',
        'time_conditions.update' => 'Update time conditions',
        'time_conditions.delete' => 'Delete time conditions',
        'cdrs.view' => 'View call detail records',
        'call_events.view' => 'View call events',
        'webhooks.view' => 'View webhooks',
        'webhooks.create' => 'Create webhooks',
        'webhooks.update' => 'Update webhooks',
        'webhooks.delete' => 'Delete webhooks',
        'device_profiles.view' => 'View device profiles',
        'device_profiles.create' => 'Create device profiles',
        'device_profiles.update' => 'Update device profiles',
        'device_profiles.delete' => 'Delete device profiles',
        'calls.originate' => 'Originate calls',
        'recordings.view' => 'View recordings',
        'recordings.delete' => 'Delete recordings',
        'recordings.download' => 'Download recordings',
        'users.view' => 'View users',
        'users.create' => 'Create users',
        'users.update' => 'Update users',
        'users.delete' => 'Delete users',
    ];

    public function handle(ModuleRegistry $registry): int
    {
        $synced = 0;

        // Sync core permissions
        foreach ($this->corePermissions as $slug => $description) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['description' => $description, 'module' => 'core']
            );
            $synced++;
        }

        // Sync module-contributed permissions
        $modulePermissions = $registry->collectPermissions();
        foreach ($modulePermissions as $slug) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['module' => 'module']
            );
            $synced++;
        }

        $this->info("Synced {$synced} permissions.");

        return self::SUCCESS;
    }
}
