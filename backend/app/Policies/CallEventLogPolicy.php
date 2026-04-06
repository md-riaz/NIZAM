<?php

namespace App\Policies;

use App\Models\User;

class CallEventLogPolicy
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
        return $user->hasPermission('call_events.view');
    }
}
