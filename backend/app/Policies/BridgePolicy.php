<?php

namespace App\Policies;

use App\Models\Bridge;
use App\Models\User;

class BridgePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('gateways.view');
    }

    public function view(User $user, Bridge $bridge): bool
    {
        return $user->organization_id === $bridge->organization_id
            && $user->hasPermission('gateways.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermission('gateways.manage');
    }

    public function update(User $user, Bridge $bridge): bool
    {
        return $user->organization_id === $bridge->organization_id
            && $user->hasPermission('gateways.manage');
    }

    public function delete(User $user, Bridge $bridge): bool
    {
        return $user->organization_id === $bridge->organization_id
            && $user->hasPermission('gateways.manage');
    }
}
