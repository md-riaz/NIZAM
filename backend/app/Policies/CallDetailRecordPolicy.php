<?php

namespace App\Policies;

use App\Models\CallDetailRecord;
use App\Models\User;

class CallDetailRecordPolicy
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
        return $user->hasPermission('cdrs.view');
    }

    public function view(User $user, CallDetailRecord $cdr): bool
    {
        return $user->tenant_id === $cdr->tenant_id
            && $user->hasPermission('cdrs.view');
    }
}
