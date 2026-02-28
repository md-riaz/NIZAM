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
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return false; // Only admins
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return false; // Only admins
    }

    public function update(User $user, User $model): bool
    {
        return false; // Only admins
    }

    public function delete(User $user, User $model): bool
    {
        return false; // Only admins
    }
}
