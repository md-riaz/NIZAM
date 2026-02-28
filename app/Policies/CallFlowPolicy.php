<?php

namespace App\Policies;

use App\Models\CallFlow;
use App\Models\User;

class CallFlowPolicy
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
        return $user->hasPermission('call_flows.view');
    }

    public function view(User $user, CallFlow $callFlow): bool
    {
        return $user->tenant_id === $callFlow->tenant_id
            && $user->hasPermission('call_flows.view');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('call_flows.create');
    }

    public function update(User $user, CallFlow $callFlow): bool
    {
        return $user->tenant_id === $callFlow->tenant_id
            && $user->hasPermission('call_flows.update');
    }

    public function delete(User $user, CallFlow $callFlow): bool
    {
        return $user->tenant_id === $callFlow->tenant_id
            && $user->hasPermission('call_flows.delete');
    }
}
