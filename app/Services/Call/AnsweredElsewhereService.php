<?php

namespace App\Services\Call;

use App\Events\CallDeliveryPushRequested;
use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use Illuminate\Support\Str;

class AnsweredElsewhereService
{
    public function __construct(
        protected TraceWriter $traceWriter,
    ) {}

    /**
     * @param  iterable<CallDeliveryAttempt>  $losingAttempts
     */
    public function notifyAnsweredElsewhere(
        CallSession $callSession,
        CallDeliveryAttempt $winningAttempt,
        iterable $losingAttempts,
    ): void {
        foreach ($losingAttempts as $losingAttempt) {
            $endpointBinding = $losingAttempt->relationLoaded('endpointBinding')
                ? $losingAttempt->endpointBinding
                : $losingAttempt->endpointBinding()->first();

            if (! $endpointBinding instanceof EndpointBinding || ! $this->supportsAnsweredElsewhere($endpointBinding)) {
                continue;
            }

            $existing = PushNotificationLog::query()
                ->where('call_session_id', $callSession->id)
                ->where('endpoint_binding_id', $endpointBinding->id)
                ->where('push_type', 'answered_elsewhere')
                ->latest('created_at')
                ->first();

            if ($existing) {
                continue;
            }

            $providerMessageId = (string) Str::uuid();
            $payload = [
                'provider_message_id' => $providerMessageId,
                'notification_type' => 'answered_elsewhere',
                'reason' => 'winner_committed',
                'call_session_id' => $callSession->id,
                'call_uuid' => $callSession->call_uuid,
                'endpoint_binding_id' => $endpointBinding->id,
                'losing_attempt_id' => $losingAttempt->id,
                'winner_attempt_id' => $winningAttempt->id,
                'winner_leg_uuid' => data_get($callSession->variables, 'winner_leg_uuid', $winningAttempt->freeswitch_leg_uuid),
            ];

            $log = $callSession->pushNotificationLogs()->create([
                'endpoint_binding_id' => $endpointBinding->id,
                'push_type' => 'answered_elsewhere',
                'provider_message_id' => $providerMessageId,
                'status' => 'queued',
                'sent_at' => now(),
                'response_payload' => $payload,
            ]);

            event(new CallDeliveryPushRequested($callSession->id, $endpointBinding->id, $payload));

            $this->traceWriter->write($callSession, 'delivery.answered_elsewhere.dispatched', [
                'attempt_id' => $losingAttempt->id,
                'endpoint_binding_id' => $endpointBinding->id,
                'push_notification_log_id' => $log->id,
                'winner_attempt_id' => $winningAttempt->id,
            ]);
        }
    }

    protected function supportsAnsweredElsewhere(EndpointBinding $endpointBinding): bool
    {
        return $endpointBinding->pushEnabled() && $endpointBinding->hasPushTokenMaterial();
    }
}
