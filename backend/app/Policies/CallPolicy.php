<?php

namespace App\Policies;

use App\Models\User;

class CallPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return null;
    }

    public function originate(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermission('calls.originate');
    }

    public function viewStatus(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermission('calls.view_status');
    }

    public function callControl(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermission('calls.control');
    }
}
