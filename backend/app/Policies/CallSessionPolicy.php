<?php

namespace App\Policies;

use App\Models\CallSession;
use App\Models\User;

/**
 * Authorizes access to call sessions and their trace data.
 *
 * Call sessions carry the same sensitivity as call detail records — who called
 * whom, when, and which endpoint answered — so they share the `cdrs.view`
 * permission rather than introducing a parallel slug.
 */
class CallSessionPolicy
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
        return $user->organization_id !== null
            && $user->hasPermission('cdrs.view');
    }

    public function view(User $user, CallSession $callSession): bool
    {
        return $user->organization_id === $callSession->organization_id
            && $user->hasPermission('cdrs.view');
    }
}
