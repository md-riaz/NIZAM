<?php

namespace App\Policies;

use App\Models\Recording;
use App\Models\User;

class RecordingPolicy
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
        return $user->hasPermission('recordings.view');
    }

    public function view(User $user, Recording $recording): bool
    {
        return $user->tenant_id === $recording->tenant_id
            && $user->hasPermission('recordings.view');
    }

    public function download(User $user, Recording $recording): bool
    {
        return $user->tenant_id === $recording->tenant_id
            && $user->hasPermission('recordings.download');
    }

    public function delete(User $user, Recording $recording): bool
    {
        return $user->tenant_id === $recording->tenant_id
            && $user->hasPermission('recordings.delete');
    }
}
