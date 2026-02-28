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
        return $user->hasPermission('extensions.view');
    }

    public function view(User $user, Extension $extension): bool
    {
        return $user->tenant_id === $extension->tenant_id
            && $user->hasPermission('extensions.view');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('extensions.create');
    }

    public function update(User $user, Extension $extension): bool
    {
        return $user->tenant_id === $extension->tenant_id
            && $user->hasPermission('extensions.update');
    }

    public function delete(User $user, Extension $extension): bool
    {
        return $user->tenant_id === $extension->tenant_id
            && $user->hasPermission('extensions.delete');
    }
}
