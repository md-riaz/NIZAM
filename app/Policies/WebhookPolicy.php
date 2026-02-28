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
        return true;
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $user->tenant_id === $webhook->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $user->tenant_id === $webhook->tenant_id;
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $user->tenant_id === $webhook->tenant_id;
    }
}
