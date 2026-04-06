<?php

namespace App\Policies;

use App\Models\Did;
use App\Models\User;

class DidPolicy
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
        return $user->hasPermission('dids.view');
    }

    public function view(User $user, Did $did): bool
    {
        return $user->tenant_id === $did->tenant_id
            && $user->hasPermission('dids.view');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('dids.create');
    }

    public function update(User $user, Did $did): bool
    {
        return $user->tenant_id === $did->tenant_id
            && $user->hasPermission('dids.update');
    }

    public function delete(User $user, Did $did): bool
    {
        return $user->tenant_id === $did->tenant_id
            && $user->hasPermission('dids.delete');
    }
}
