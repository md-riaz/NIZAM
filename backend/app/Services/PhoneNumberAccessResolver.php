<?php

namespace App\Services;

use App\Models\DeviceProfile;
use App\Models\Did;
use App\Models\Extension;
use App\Models\User;

class PhoneNumberAccessResolver
{
    /**
     * @return array{name: string, number: string}
     */
    public function resolveForExtension(Extension $extension): array
    {
        $extension->loadMissing('user', 'ownerDeviceProfile');

        $did = $this->resolveDidForExtension($extension);

        return [
            'name' => $did?->description ?: $extension->effective_caller_id_name ?: $extension->first_name ?: $extension->extension,
            'number' => $did?->number ?: $extension->effective_caller_id_number ?: $extension->extension,
        ];
    }

    public function resolveDidForExtension(Extension $extension): ?Did
    {
        if ($extension->user) {
            return $this->resolveDidForUser($extension->user);
        }

        if ($extension->ownerDeviceProfile) {
            return $this->resolveDidForDevice($extension->ownerDeviceProfile);
        }

        return null;
    }

    public function resolveDidForUser(User $user): ?Did
    {
        $user->loadMissing('defaultOutboundDid');

        return $user->resolveOutboundDid();
    }

    public function resolveDidForDevice(DeviceProfile $deviceProfile): ?Did
    {
        $deviceProfile->loadMissing('defaultOutboundDid');

        return $deviceProfile->resolveOutboundDid();
    }
}
