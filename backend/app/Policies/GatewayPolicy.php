<?php

namespace App\Policies;

use App\Models\Gateway;
use App\Models\User;

class GatewayPolicy
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

    public function view(User $user, Gateway $gateway): bool
    {
        return $user->organization_id === $gateway->organization_id
            && $user->hasPermission('gateways.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermission('gateways.manage');
    }

    public function update(User $user, Gateway $gateway): bool
    {
        return $user->organization_id === $gateway->organization_id
            && $user->hasPermission('gateways.manage');
    }

    public function delete(User $user, Gateway $gateway): bool
    {
        return $user->organization_id === $gateway->organization_id
            && $user->hasPermission('gateways.manage');
    }
}
