<?php

namespace App\Services;

use App\Models\Extension;
use App\Models\Organization;
use Illuminate\Support\Str;

class StarterExtensionProvisioningService
{
    public function __construct(
        protected ExtensionNumberingService $extensionNumberingService,
    ) {}

    public function provision(Organization $organization): Extension
    {
        if ($organization->extensions()->exists()) {
            return $organization->extensions()->orderBy('extension')->firstOrFail();
        }

        $extensionNumber = $this->extensionNumberingService->firstAvailableForOrganization($organization);

        return $organization->extensions()->create([
            'extension' => $extensionNumber,
            'password' => Str::password(16, true, true, false, false),
            'first_name' => 'Main',
            'last_name' => 'User',
            'effective_caller_id_name' => 'Main User',
            'voicemail_enabled' => true,
            'voicemail_pin' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'is_active' => true,
        ]);
    }
}
