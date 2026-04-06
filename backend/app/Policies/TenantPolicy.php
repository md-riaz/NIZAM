<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Admins can perform any action.
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
        return $user->hasPermission('tenants.view');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id
            && $user->hasPermission('tenants.view');
    }

    public function create(User $user): bool
    {
        return false; // Only admins can create tenants
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return false; // Only admins can update tenants
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return false; // Only admins can delete tenants
    }
}
