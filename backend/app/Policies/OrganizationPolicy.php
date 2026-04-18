<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Admins can perform any action.
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
        return $user->hasPermission('organizations.view');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id
            && $user->hasPermission('organizations.view');
    }

    public function create(User $user): bool
    {
        return false; // Only admins can create organizations
    }

    public function update(User $user, Organization $organization): bool
    {
        return false; // Only admins can update organizations
    }

    public function delete(User $user, Organization $organization): bool
    {
        return false; // Only admins can delete organizations
    }
}
