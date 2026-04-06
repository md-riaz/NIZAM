<?php

namespace App\Services\Call;

use App\Models\CallSession;
use App\Models\Tenant;

class CallDeliveryEntrypointService
{
    public function __construct(
        protected CallSessionService $callSessionService,
        protected DeliveryTargetResolver $deliveryTargetResolver,
        protected EndpointResolver $endpointResolver,
        protected ReachabilityResolver $reachabilityResolver,
        protected DeliveryPlanner $deliveryPlanner,
        protected CallOfferExecutor $callOfferExecutor,
        protected TraceWriter $traceWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function enter(Tenant $tenant, string $callUuid, array $context = []): CallSession
    {
        $session = $this->callSessionService->getOrCreateInboundSession(
            tenant: $tenant,
            callUuid: $callUuid,
            did: null,
            variables: $this->sessionVariables($context),
        )->loadMissing('tenant');

        $this->persistEntrypointMetadata($session, $context);

        if (filled(data_get($session->variables, 'winner_attempt_id'))) {
            $this->traceWriter->write($session, 'delivery.entrypoint.skipped', [
                'reason' => 'winner_already_committed',
                'call_uuid' => $callUuid,
            ]);

            return $session->fresh(['tenant']);
        }

        if ($this->callSessionService->hasActiveDeliveryAttempts($session)) {
            $this->traceWriter->write($session, 'delivery.entrypoint.skipped', [
                'reason' => 'active_attempts_exist',
                'call_uuid' => $callUuid,
                'active_attempt_count' => $session->activeDeliveryAttempts()->count(),
            ]);

            return $session->fresh(['tenant']);
        }

        $targetSet = $this->deliveryTargetResolver->resolve($session);
        $candidateSet = $this->endpointResolver->resolve($targetSet);
        $decisionSet = $this->reachabilityResolver->resolve($session, $candidateSet);
        $plan = $this->deliveryPlanner->createDeliveryPlan($session, $candidateSet, $decisionSet);

        $attemptResults = $this->callOfferExecutor->executePlan($plan, [
            'caller_leg_uuid' => data_get($context, 'caller_leg_uuid', $callUuid),
            'caller_id_name' => (string) data_get($context, 'caller_id_name', data_get($session->variables, 'caller_id_name', 'Inbound Call')),
            'caller_id_number' => (string) data_get($context, 'caller_id_number', data_get($session->variables, 'caller_id_number', 'unknown')),
        ]);

        $session->refresh();
        $lateJoinBindings = data_get($plan->wakeWindow, 'late_join_bindings', []);
        $deliveryWakeWindowUntil = collect(is_array($lateJoinBindings) ? $lateJoinBindings : [])
            ->pluck('late_join_window_until')
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->sort()
            ->last();

        $session->forceFill([
            'state' => filled(data_get($session->variables, 'winner_attempt_id')) ? $session->state : 'parked',
            'variables' => [
                ...($session->variables ?? []),
                'delivery_orchestration_started_at' => data_get($session->variables, 'delivery_orchestration_started_at', now()->toIso8601String()),
                'delivery_wake_window_until' => $deliveryWakeWindowUntil,
                'delivery_late_join_bindings' => $lateJoinBindings,
                'delivery_plan' => [
                    'immediate_sip_attempt_count' => count($plan->immediateSipWave),
                    'immediate_push_attempt_count' => count($plan->immediatePushWave),
                    'delayed_pstn_attempt_count' => count($plan->delayedPstnWave),
                    'wake_window_seconds' => $plan->wakeWindowSeconds,
                ],
                'delivery_attempt_ids' => $attemptResults,
            ],
        ])->save();

        $this->traceWriter->write($session, 'delivery.entrypoint.orchestrated', [
            'call_uuid' => $callUuid,
            'target_type' => data_get($session->variables, 'nizam_delivery_target_type'),
            'target_id' => data_get($session->variables, 'nizam_delivery_target_id'),
            'attempt_results' => $attemptResults,
        ]);

        return $session->fresh(['tenant']);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function sessionVariables(array $context): array
    {
        return array_filter([
            'nizam_delivery_target_type' => data_get($context, 'target_type'),
            'nizam_delivery_target_id' => data_get($context, 'target_id'),
            'nizam_call_uuid' => data_get($context, 'caller_leg_uuid'),
            'caller_id_name' => data_get($context, 'caller_id_name'),
            'caller_id_number' => data_get($context, 'caller_id_number'),
            'destination_number' => data_get($context, 'destination_number'),
            'domain' => data_get($context, 'domain'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function persistEntrypointMetadata(CallSession $session, array $context): void
    {
        $invocations = (int) data_get($session->variables, 'delivery_entrypoint_invocations', 0) + 1;

        $session->forceFill([
            'state' => filled(data_get($session->variables, 'winner_attempt_id')) ? $session->state : 'parked',
            'variables' => [
                ...($session->variables ?? []),
                ...$this->sessionVariables($context),
                'delivery_entrypoint_invocations' => $invocations,
                'delivery_entrypoint_last_invoked_at' => now()->toIso8601String(),
                'delivery_entrypoint_last_context' => array_filter([
                    'target_type' => data_get($context, 'target_type'),
                    'target_id' => data_get($context, 'target_id'),
                    'caller_leg_uuid' => data_get($context, 'caller_leg_uuid'),
                ], static fn ($value) => $value !== null && $value !== ''),
            ],
        ])->save();
    }
}
