<?php

namespace App\Observers;

use App\Models\Extension;
use App\Services\Flow\FlowArtifactService;
use App\Services\WallboardProjectionService;
use Illuminate\Support\Facades\Log;

class ExtensionObserver
{
    use RebuildsOrganizationManifest;

    /**
     * Handle the Extension "updated" event.
     *
     * When extension credentials or identity fields change,
     * mark associated device profiles for reprovisioning.
     */
    public function updated(Extension $extension): void
    {
        $provisioningFields = [
            'password',
            'extension',
            'first_name',
            'last_name',
            'effective_caller_id_name',
            'effective_caller_id_number',
            'default_outbound_did_id',
            'voicemail_enabled',
        ];
        $changed = array_keys($extension->getChanges());

        if (in_array('extension', $changed, true) || in_array('is_active', $changed, true)) {
            app(FlowArtifactService::class)->refreshTeamRoutingArtifactsForExtension($extension);
        }

        if (empty(array_intersect($changed, $provisioningFields))) {
            return;
        }

        $profiles = $extension->deviceProfiles()->where('is_active', true)->get();

        if ($profiles->isEmpty()) {
            return;
        }

        $extension->deviceProfiles()->where('is_active', true)->update([
            'updated_at' => now(),
        ]);

        app(WallboardProjectionService::class)->refreshExtensionProjection($extension);

        Log::info('Device profiles marked for reprovisioning', [
            'extension_id' => $extension->id,
            'extension' => $extension->extension,
            'changed_fields' => array_intersect($changed, $provisioningFields),
            'profile_count' => $profiles->count(),
        ]);
    }

    public function deleted(Extension $extension): void
    {
        app(FlowArtifactService::class)->refreshTeamRoutingArtifactsForExtension($extension);
    }
}
