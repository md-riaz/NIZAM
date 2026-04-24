<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admins can perform any action on users.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' && $user->organization_id !== null;
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id
            || ($user->role === 'admin'
                && $user->organization_id !== null
                && $user->organization_id === $model->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' && $user->organization_id !== null;
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === 'admin'
            && $user->organization_id !== null
            && $user->organization_id === $model->organization_id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->role === 'admin'
            && $user->organization_id !== null
            && $user->organization_id === $model->organization_id;
    }
}
