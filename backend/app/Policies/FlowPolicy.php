<?php

namespace App\Policies;

use App\Models\Flow;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Authorizes call-flow access.
 *
 * The slugs are the declared `flows.*` names. This class used to check
 * `view-flows` / `manage-flows`, which nothing declared, so the rows never
 * existed and — once permissions became deny-by-default — flow management was
 * unreachable for everyone below admin.
 */
class FlowPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('flows.view');
    }

    public function view(User $user, Flow $flow): bool
    {
        if ($user->organization_id !== $flow->organization_id) {
            return false;
        }

        return $user->hasPermission('flows.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('flows.create');
    }

    public function update(User $user, Flow $flow): bool
    {
        if ($user->organization_id !== $flow->organization_id) {
            return false;
        }

        return $user->hasPermission('flows.update');
    }

    public function delete(User $user, Flow $flow): bool
    {
        if ($user->organization_id !== $flow->organization_id) {
            return false;
        }

        return $user->hasPermission('flows.delete');
    }
}
