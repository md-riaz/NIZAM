<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
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
        return $user->hasPermission('audit_logs.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->tenant_id === $auditLog->tenant_id
            && $user->hasPermission('audit_logs.view');
    }
}
