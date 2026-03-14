<?php

namespace App\Services\Call;

use App\Models\CallSession;
use App\Models\Did;
use App\Models\Tenant;

class CallSessionService
{
    public function __construct(
        protected TraceWriter $traceWriter
    ) {}

    public function getOrCreateInboundSession(
        Tenant $tenant,
        string $callUuid,
        ?Did $did = null,
        array $variables = []
    ): CallSession {
        $session = CallSession::firstOrNew(['call_uuid' => $callUuid]);

        $wasRecentlyCreated = ! $session->exists;

        $session->fill([
            'tenant_id' => $tenant->id,
            'did_id' => $did?->id,
            'state' => $session->state ?: 'initiated',
            'variables' => array_merge($session->variables ?? [], $variables),
            'started_at' => $session->started_at ?? now(),
        ]);

        $session->save();

        if ($wasRecentlyCreated) {
            $this->traceWriter->write($session, 'call_session.created', [
                'tenant_id' => $tenant->id,
                'did_id' => $did?->id,
            ]);
        }

        return $session;
    }

    public function markEnded(CallSession $session, string $state = 'ended', array $tracePayload = []): CallSession
    {
        $session->forceFill([
            'state' => $state,
            'ended_at' => now(),
            'lock_version' => $session->lock_version + 1,
        ])->save();

        $this->traceWriter->write($session, 'call_session.ended', $tracePayload);

        return $session;
    }
}
