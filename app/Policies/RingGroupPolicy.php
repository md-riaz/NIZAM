<?php

namespace App\Policies;

use App\Models\RingGroup;
use App\Models\User;

class RingGroupPolicy
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
        return true;
    }

    public function view(User $user, RingGroup $ringGroup): bool
    {
        return $user->tenant_id === $ringGroup->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, RingGroup $ringGroup): bool
    {
        return $user->tenant_id === $ringGroup->tenant_id;
    }

    public function delete(User $user, RingGroup $ringGroup): bool
    {
        return $user->tenant_id === $ringGroup->tenant_id;
    }
}
