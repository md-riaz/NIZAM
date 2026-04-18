<?php

namespace App\Policies;

use App\Models\RingGroup;
use App\Models\User;

class RingGroupPolicy
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
        return $user->hasPermission('ring_groups.view');
    }

    public function view(User $user, RingGroup $ringGroup): bool
    {
        return $user->organization_id === $ringGroup->organization_id
            && $user->hasPermission('ring_groups.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermission('ring_groups.create');
    }

    public function update(User $user, RingGroup $ringGroup): bool
    {
        return $user->organization_id === $ringGroup->organization_id
            && $user->hasPermission('ring_groups.update');
    }

    public function delete(User $user, RingGroup $ringGroup): bool
    {
        return $user->organization_id === $ringGroup->organization_id
            && $user->hasPermission('ring_groups.delete');
    }
}
