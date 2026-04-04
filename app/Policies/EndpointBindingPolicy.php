<?php

namespace App\Policies;

use App\Models\EndpointBinding;
use App\Models\User;

class EndpointBindingPolicy
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
        return $user->hasPermission('endpoint_bindings.view');
    }

    public function view(User $user, EndpointBinding $endpointBinding): bool
    {
        return $user->tenant_id === $endpointBinding->tenant_id
            && $user->hasPermission('endpoint_bindings.view');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('endpoint_bindings.create');
    }

    public function update(User $user, EndpointBinding $endpointBinding): bool
    {
        return $user->tenant_id === $endpointBinding->tenant_id
            && $user->hasPermission('endpoint_bindings.update');
    }

    public function delete(User $user, EndpointBinding $endpointBinding): bool
    {
        return $user->tenant_id === $endpointBinding->tenant_id
            && $user->hasPermission('endpoint_bindings.delete');
    }
}
