<?php

namespace App\Policies;

use App\Models\CallSession;
use App\Models\Organization;
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

    /**
     * `EnsureOrganizationAccess` already rejects a tenant user who requests
     * another organization's route, so this is defence in depth rather than the
     * only barrier — but the whole point of this class is to not depend on a
     * single layer, so the requested organization is checked here too.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id
            && $user->hasPermission('cdrs.view');
    }

    public function view(User $user, CallSession $callSession): bool
    {
        return $user->organization_id === $callSession->organization_id
            && $user->hasPermission('cdrs.view');
    }
}
