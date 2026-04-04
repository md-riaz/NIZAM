<?php

namespace App\Services\Call;

use App\Events\CallDeliveryPushRequested;
use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\PushNotificationLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CallOfferExecutor
{
    public function __construct(
        protected OfferCommandDispatcher $offerCommandDispatcher,
        protected TraceWriter $traceWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, array<int, string>>
     */
    public function executePlan(DeliveryPlan $deliveryPlan, array $context = []): array
    {
        /** @var CallSession $callSession */
        $callSession = CallSession::query()->with('tenant')->findOrFail($deliveryPlan->callSessionId);

        $results = [
            'sip_attempt_ids' => [],
            'push_attempt_ids' => [],
            'pstn_attempt_ids' => [],
        ];

        foreach ($deliveryPlan->immediateSipWave as $item) {
            $attempt = $this->ensureAttempt($callSession, $item);

            if ($attempt->status !== CallDeliveryAttempt::STATUS_INITIATED) {
                $result = $this->offerCommandDispatcher->originateSip($item, $this->buildContext($callSession, $context, $item));
                $attempt = $this->syncAttemptWithOfferResult($attempt, $item, $result);
            }

            $results['sip_attempt_ids'][] = $attempt->id;
        }

        foreach ($deliveryPlan->immediatePushWave as $item) {
            $attempt = $this->ensureAttempt($callSession, $item);
            $pushLog = $this->ensurePushLog($callSession, $item, $context);

            if ($attempt->status !== CallDeliveryAttempt::STATUS_INITIATED) {
                $attempt->forceFill([
                    'status' => CallDeliveryAttempt::STATUS_INITIATED,
                    'started_at' => $attempt->started_at ?? now(),
                    'failure_reason' => null,
                    'metadata' => $this->mergeAttemptMetadata($attempt, $item, [
                        'push_notification_log_id' => $pushLog->id,
                        'push_status' => $pushLog->status,
                    ]),
                ])->save();

                $this->traceWriter->write($callSession, 'delivery.push.dispatched', [
                    'endpoint_binding_id' => $item->candidate->endpointBindingId,
                    'attempt_id' => $attempt->id,
                    'push_notification_log_id' => $pushLog->id,
                ]);
            }

            $results['push_attempt_ids'][] = $attempt->id;
        }

        foreach ($deliveryPlan->delayedPstnWave as $item) {
            $attempt = $this->ensureAttempt($callSession, $item);

            if ($attempt->status !== CallDeliveryAttempt::STATUS_INITIATED) {
                $result = $this->offerCommandDispatcher->originatePstn($item, $this->buildContext($callSession, $context, $item));
                $attempt = $this->syncAttemptWithOfferResult($attempt, $item, $result);
            }

            $results['pstn_attempt_ids'][] = $attempt->id;
        }

        return $results;
    }

    protected function ensureAttempt(CallSession $callSession, DeliveryPlanItem $item): CallDeliveryAttempt
    {
        return DB::transaction(function () use ($callSession, $item): CallDeliveryAttempt {
            $existing = CallDeliveryAttempt::query()
                ->where('call_session_id', $callSession->id)
                ->where('endpoint_binding_id', $item->candidate->endpointBindingId)
                ->where('attempt_type', $item->attemptType)
                ->whereIn('status', [
                    CallDeliveryAttempt::STATUS_PLANNED,
                    CallDeliveryAttempt::STATUS_INITIATED,
                    CallDeliveryAttempt::STATUS_RINGING,
                    CallDeliveryAttempt::STATUS_ANSWERED,
                    CallDeliveryAttempt::STATUS_CONFIRMED,
                ])
                ->latest('created_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            $attempt = $callSession->deliveryAttempts()->create([
                'endpoint_binding_id' => $item->candidate->endpointBindingId,
                'attempt_type' => $item->attemptType,
                'status' => CallDeliveryAttempt::STATUS_PLANNED,
                'started_at' => now(),
                'metadata' => $this->baseAttemptMetadata($item),
            ]);

            $this->traceWriter->write($callSession, 'delivery.attempt.planned', [
                'attempt_id' => $attempt->id,
                'endpoint_binding_id' => $item->candidate->endpointBindingId,
                'attempt_type' => $item->attemptType,
                'wave' => $item->wave,
            ]);

            return $attempt;
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function ensurePushLog(CallSession $callSession, DeliveryPlanItem $item, array $context): PushNotificationLog
    {
        $existing = PushNotificationLog::query()
            ->where('call_session_id', $callSession->id)
            ->where('endpoint_binding_id', $item->candidate->endpointBindingId)
            ->where('push_type', 'wake')
            ->latest('created_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $providerMessageId = (string) Str::uuid();
        $payload = $this->buildPushPayload($callSession, $item, $context, $providerMessageId);

        $log = $callSession->pushNotificationLogs()->create([
            'endpoint_binding_id' => $item->candidate->endpointBindingId,
            'push_type' => 'wake',
            'provider_message_id' => $providerMessageId,
            'status' => 'queued',
            'sent_at' => now(),
            'response_payload' => $payload,
        ]);

        event(new CallDeliveryPushRequested($callSession->id, $item->candidate->endpointBindingId, $payload));

        return $log;
    }

    protected function syncAttemptWithOfferResult(
        CallDeliveryAttempt $attempt,
        DeliveryPlanItem $item,
        OfferCommandResult $result,
    ): CallDeliveryAttempt {
        $attempt->forceFill([
            'status' => $result->executed
                ? CallDeliveryAttempt::STATUS_INITIATED
                : CallDeliveryAttempt::STATUS_FAILED,
            'freeswitch_leg_uuid' => $result->legUuid,
            'started_at' => $attempt->started_at ?? now(),
            'ended_at' => $result->executed ? null : now(),
            'failure_reason' => $result->failureReason,
            'metadata' => $this->mergeAttemptMetadata($attempt, $item, [
                'offer_response' => $result->response,
                'offer_metadata' => $result->metadata,
            ]),
        ])->save();

        $this->traceWriter->write($attempt->callSession, $result->executed ? 'delivery.offer.initiated' : 'delivery.offer.failed', [
            'attempt_id' => $attempt->id,
            'endpoint_binding_id' => $item->candidate->endpointBindingId,
            'attempt_type' => $item->attemptType,
            'wave' => $item->wave,
            'leg_uuid' => $attempt->freeswitch_leg_uuid,
            'failure_reason' => $attempt->failure_reason,
        ]);

        return $attempt->fresh();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function buildContext(CallSession $callSession, array $context, DeliveryPlanItem $item): array
    {
        return [
            ...$context,
            'call_session_id' => $callSession->id,
            'call_uuid' => $callSession->call_uuid,
            'caller_leg_uuid' => data_get($context, 'caller_leg_uuid', $callSession->call_uuid),
            'caller_id_name' => (string) data_get($context, 'caller_id_name', data_get($callSession->variables, 'caller_id_name', 'Inbound Call')),
            'caller_id_number' => (string) data_get($context, 'caller_id_number', data_get($callSession->variables, 'caller_id_number', 'unknown')),
            'tenant_domain' => data_get($callSession->tenant, 'domain'),
            'wave' => $item->wave,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseAttemptMetadata(DeliveryPlanItem $item): array
    {
        return [
            'wave' => $item->wave,
            'delay_seconds' => $item->delaySeconds,
            'requires_confirmation' => $item->requiresConfirmation,
            'late_join_window_until' => $item->lateJoinWindowUntil,
            'candidate' => $item->candidate->toArray(),
            'decision' => $item->decision->toArray(),
            'planner_metadata' => $item->metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function mergeAttemptMetadata(CallDeliveryAttempt $attempt, DeliveryPlanItem $item, array $extra): array
    {
        return [
            ...$this->baseAttemptMetadata($item),
            ...Arr::wrap($attempt->metadata),
            ...$extra,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function buildPushPayload(
        CallSession $callSession,
        DeliveryPlanItem $item,
        array $context,
        string $providerMessageId,
    ): array {
        return [
            'provider_message_id' => $providerMessageId,
            'call_session_id' => $callSession->id,
            'call_uuid' => $callSession->call_uuid,
            'endpoint_binding_id' => $item->candidate->endpointBindingId,
            'attempt_type' => $item->attemptType,
            'wave' => $item->wave,
            'late_join_window_until' => $item->lateJoinWindowUntil,
            'caller_id_name' => (string) data_get($context, 'caller_id_name', data_get($callSession->variables, 'caller_id_name')),
            'caller_id_number' => (string) data_get($context, 'caller_id_number', data_get($callSession->variables, 'caller_id_number')),
            'tenant_domain' => data_get($callSession->tenant, 'domain'),
        ];
    }
}
