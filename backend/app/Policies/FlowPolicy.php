<?php

namespace App\Policies;

use App\Models\Flow;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

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
        return $user->hasPermission('view-flows');
    }

    public function view(User $user, Flow $flow): bool
    {
        if ($user->organization_id !== $flow->organization_id) {
            return false;
        }

        return $user->hasPermission('view-flows');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-flows');
    }

    public function update(User $user, Flow $flow): bool
    {
        if ($user->organization_id !== $flow->organization_id) {
            return false;
        }

        return $user->hasPermission('manage-flows');
    }

    public function delete(User $user, Flow $flow): bool
    {
        if ($user->organization_id !== $flow->organization_id) {
            return false;
        }

        return $user->hasPermission('manage-flows');
    }
}
