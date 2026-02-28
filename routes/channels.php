<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen on the channel.
|
*/

Broadcast::channel('tenant.{tenantId}.calls', function ($user, string $tenantId) {
    if ($user->role === 'admin') {
        return true;
    }

    return $user->tenant_id === $tenantId;
});

Broadcast::channel('tenant.{tenantId}.calls.{eventType}', function ($user, string $tenantId, string $eventType) {
    if ($user->role === 'admin') {
        return true;
    }

    return $user->tenant_id === $tenantId;
});
