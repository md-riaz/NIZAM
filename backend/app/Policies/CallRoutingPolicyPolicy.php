<?php

namespace App\Policies;

use App\Models\CallRoutingPolicy;
use App\Models\User;

class CallRoutingPolicyPolicy
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
        return $user->hasPermission('call_routing_policies.view');
    }

    public function view(User $user, CallRoutingPolicy $policy): bool
    {
        return $user->organization_id === $policy->organization_id
            && $user->hasPermission('call_routing_policies.view');
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $user->hasPermission('call_routing_policies.create');
    }

    public function update(User $user, CallRoutingPolicy $policy): bool
    {
        return $user->organization_id === $policy->organization_id
            && $user->hasPermission('call_routing_policies.update');
    }

    public function delete(User $user, CallRoutingPolicy $policy): bool
    {
        return $user->organization_id === $policy->organization_id
            && $user->hasPermission('call_routing_policies.delete');
    }
}
