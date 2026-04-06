<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;

class WebhookPolicy
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
        return $user->hasPermission('webhooks.view');
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $user->tenant_id === $webhook->tenant_id
            && $user->hasPermission('webhooks.view');
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null
            && $user->hasPermission('webhooks.create');
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $user->tenant_id === $webhook->tenant_id
            && $user->hasPermission('webhooks.update');
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $user->tenant_id === $webhook->tenant_id
            && $user->hasPermission('webhooks.delete');
    }
}
