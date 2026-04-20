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

Broadcast::channel('organization.{organizationId}.calls', function ($user, string $organizationId) {
    if ($user->role === 'admin') {
        return true;
    }

    return $user->organization_id === $organizationId;
});

Broadcast::channel('organization.{organizationId}.calls.{eventType}', function ($user, string $organizationId, string $eventType) {
    if ($user->role === 'admin') {
        return true;
    }

    return $user->organization_id === $organizationId;
});

Broadcast::channel('organization.{organizationId}.contact-center', function ($user, string $organizationId) {
    if ($user->role === 'admin') {
        return true;
    }

    return $user->organization_id === $organizationId;
});

Broadcast::channel('organization.{organizationId}.contact-center.{eventType}', function ($user, string $organizationId, string $eventType) {
    if ($user->role === 'admin') {
        return true;
    }

    return $user->organization_id === $organizationId;
});
