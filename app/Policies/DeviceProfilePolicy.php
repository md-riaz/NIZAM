<?php

namespace App\Policies;

use App\Models\DeviceProfile;
use App\Models\User;

class DeviceProfilePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('device_profiles.view');
    }

    public function view(User $user, DeviceProfile $deviceProfile): bool
    {
        return $user->tenant_id === $deviceProfile->tenant_id
            && $user->hasPermission('device_profiles.view');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('device_profiles.create');
    }

    public function update(User $user, DeviceProfile $deviceProfile): bool
    {
        return $user->tenant_id === $deviceProfile->tenant_id
            && $user->hasPermission('device_profiles.update');
    }

    public function delete(User $user, DeviceProfile $deviceProfile): bool
    {
        return $user->tenant_id === $deviceProfile->tenant_id
            && $user->hasPermission('device_profiles.delete');
    }
}
