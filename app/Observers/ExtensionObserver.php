<?php

namespace App\Observers;

use App\Models\Extension;
use Illuminate\Support\Facades\Log;

class ExtensionObserver
{
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
            'directory_first_name',
            'directory_last_name',
            'effective_caller_id_name',
            'effective_caller_id_number',
            'voicemail_enabled',
        ];

        $changed = array_keys($extension->getChanges());

        if (empty(array_intersect($changed, $provisioningFields))) {
            return;
        }

        $profiles = $extension->deviceProfiles()->where('is_active', true)->get();

        if ($profiles->isEmpty()) {
            return;
        }

        // Touch updated_at on associated device profiles to signal reprovisioning needed
        $extension->deviceProfiles()->where('is_active', true)->update([
            'updated_at' => now(),
        ]);

        Log::info('Device profiles marked for reprovisioning', [
            'extension_id' => $extension->id,
            'extension' => $extension->extension,
            'changed_fields' => array_intersect($changed, $provisioningFields),
            'profile_count' => $profiles->count(),
        ]);
    }
}
