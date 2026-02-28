<?php

namespace App\Policies;

use App\Models\Extension;
use App\Models\User;

class ExtensionPolicy
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

    public function view(User $user, Extension $extension): bool
    {
        return $user->tenant_id === $extension->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Extension $extension): bool
    {
        return $user->tenant_id === $extension->tenant_id;
    }

    public function delete(User $user, Extension $extension): bool
    {
        return $user->tenant_id === $extension->tenant_id;
    }
}
