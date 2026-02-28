<?php

namespace App\Policies;

use App\Models\Ivr;
use App\Models\User;

class IvrPolicy
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
        return $user->hasPermission('ivrs.view');
    }

    public function view(User $user, Ivr $ivr): bool
    {
        return $user->tenant_id === $ivr->tenant_id
            && $user->hasPermission('ivrs.view');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('ivrs.create');
    }

    public function update(User $user, Ivr $ivr): bool
    {
        return $user->tenant_id === $ivr->tenant_id
            && $user->hasPermission('ivrs.update');
    }

    public function delete(User $user, Ivr $ivr): bool
    {
        return $user->tenant_id === $ivr->tenant_id
            && $user->hasPermission('ivrs.delete');
    }
}
