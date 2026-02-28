<?php

namespace App\Policies;

use App\Models\TimeCondition;
use App\Models\User;

class TimeConditionPolicy
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
        return $user->hasPermission('time_conditions.view');
    }

    public function view(User $user, TimeCondition $timeCondition): bool
    {
        return $user->tenant_id === $timeCondition->tenant_id
            && $user->hasPermission('time_conditions.view');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('time_conditions.create');
    }

    public function update(User $user, TimeCondition $timeCondition): bool
    {
        return $user->tenant_id === $timeCondition->tenant_id
            && $user->hasPermission('time_conditions.update');
    }

    public function delete(User $user, TimeCondition $timeCondition): bool
    {
        return $user->tenant_id === $timeCondition->tenant_id
            && $user->hasPermission('time_conditions.delete');
    }
}
