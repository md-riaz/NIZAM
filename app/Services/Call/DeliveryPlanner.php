<?php

namespace App\Services\Call;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use Illuminate\Support\Carbon;

class DeliveryPlanner
{
    public function createDeliveryPlan(
        CallSession $callSession,
        EndpointCandidateSet $candidateSet,
        ReachabilityDecisionSet $decisionSet,
    ): DeliveryPlan {
        $wakeWindowSeconds = $this->wakeWindowSeconds($callSession, $decisionSet);
        $decisionIndex = $decisionSet->collect()->keyBy('endpointBindingId');

        $immediateSipWave = [];
        $immediatePushWave = [];
        $delayedPstnWave = [];
        $wakeEligibleBindings = [];

        foreach ($candidateSet->candidates as $candidate) {
            /** @var ReachabilityDecision|null $decision */
            $decision = $decisionIndex->get($candidate->endpointBindingId);

            if (! $decision) {
                continue;
            }

            if ($decision->canRingNow) {
                $immediateSipWave[] = new DeliveryPlanItem(
                    candidate: $candidate,
                    decision: $decision,
                    wave: 'immediate_sip',
                    attemptType: CallDeliveryAttempt::TYPE_SIP,
                    metadata: [
                        'owner_type' => $candidate->ownerType,
                        'owner_id' => $candidate->ownerId,
                        'source_path' => $candidate->sourcePath,
                    ],
                );
            }

            if ($decision->shouldSendPush) {
                $immediatePushWave[] = new DeliveryPlanItem(
                    candidate: $candidate,
                    decision: $decision,
                    wave: 'immediate_push',
                    attemptType: CallDeliveryAttempt::TYPE_PUSH,
                    lateJoinWindowUntil: $decision->allowLateJoinWindowUntil,
                    metadata: [
                        'late_join_allowed' => $decision->allowLateJoinWindowUntil !== null,
                        'late_join_attempt_type' => CallDeliveryAttempt::TYPE_LATE_SIP,
                        'owner_type' => $candidate->ownerType,
                        'owner_id' => $candidate->ownerId,
                        'source_path' => $candidate->sourcePath,
                    ],
                );

                if ($decision->allowLateJoinWindowUntil !== null) {
                    $wakeEligibleBindings[$candidate->endpointBindingId] = [
                        'late_join_window_until' => $decision->allowLateJoinWindowUntil,
                        'owner_type' => $candidate->ownerType,
                        'owner_id' => $candidate->ownerId,
                        'source_path' => $candidate->sourcePath,
                    ];
                }
            }

            if ($decision->shouldOfferPstn) {
                $delayedPstnWave[] = new DeliveryPlanItem(
                    candidate: $candidate,
                    decision: $decision,
                    wave: 'delayed_pstn',
                    attemptType: CallDeliveryAttempt::TYPE_PSTN,
                    delaySeconds: $this->pstnDelaySeconds($callSession),
                    requiresConfirmation: $candidate->forwardRequiresConfirm,
                    metadata: [
                        'confirmation_required' => $candidate->forwardRequiresConfirm,
                        'owner_type' => $candidate->ownerType,
                        'owner_id' => $candidate->ownerId,
                        'source_path' => $candidate->sourcePath,
                    ],
                );
            }
        }

        $sortByPriority = static fn (DeliveryPlanItem $left, DeliveryPlanItem $right): int => [
            $left->candidate->priority,
            $left->candidate->endpointBindingId,
        ] <=> [
            $right->candidate->priority,
            $right->candidate->endpointBindingId,
        ];

        usort($immediateSipWave, $sortByPriority);
        usort($immediatePushWave, $sortByPriority);
        usort($delayedPstnWave, $sortByPriority);

        return new DeliveryPlan(
            callSessionId: $callSession->id,
            wakeWindowSeconds: $wakeWindowSeconds,
            immediateSipWave: $immediateSipWave,
            immediatePushWave: $immediatePushWave,
            delayedPstnWave: $delayedPstnWave,
            wakeWindow: [
                'seconds' => $wakeWindowSeconds,
                'late_join_bindings' => $wakeEligibleBindings,
            ],
            cancellationPolicy: [
                'cancel_active_attempts_on_winner' => true,
                'send_answered_elsewhere_to_non_winners' => true,
                'suppress_late_join_after_winner' => true,
                'pstn_confirmation_required_attempt_types' => $this->pstnConfirmationAttemptTypes($delayedPstnWave),
            ],
            metadata: [
                ...$candidateSet->metadata,
                ...$decisionSet->metadata,
                'planned_at' => Carbon::now()->toIso8601String(),
                'immediate_sip_attempt_count' => count($immediateSipWave),
                'immediate_push_attempt_count' => count($immediatePushWave),
                'delayed_pstn_attempt_count' => count($delayedPstnWave),
                'route_origins' => array_values(array_unique(array_filter(array_map(
                    static fn (EndpointCandidate $candidate): ?string => data_get($candidate->sourcePath, '0.type'),
                    $candidateSet->candidates,
                )))),
            ],
        );
    }

    protected function wakeWindowSeconds(CallSession $callSession, ReachabilityDecisionSet $decisionSet): int
    {
        $sessionOverride = data_get($callSession->variables, 'delivery_wake_window_seconds');

        if (is_numeric($sessionOverride)) {
            return max(1, (int) $sessionOverride);
        }

        $metadataValue = data_get($decisionSet->metadata, 'wake_window_seconds');

        if (is_numeric($metadataValue)) {
            return max(1, (int) $metadataValue);
        }

        return max(1, (int) config('telephony.call_delivery.wake_window_seconds', 30));
    }

    protected function pstnDelaySeconds(CallSession $callSession): int
    {
        $sessionOverride = data_get($callSession->variables, 'delivery_pstn_delay_seconds');

        if (is_numeric($sessionOverride)) {
            return max(0, (int) $sessionOverride);
        }

        return max(0, (int) config('telephony.call_delivery.pstn_delay_seconds', 8));
    }

    /**
     * @param  list<DeliveryPlanItem>  $delayedPstnWave
     * @return list<string>
     */
    protected function pstnConfirmationAttemptTypes(array $delayedPstnWave): array
    {
        if ($delayedPstnWave === []) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (DeliveryPlanItem $item): string => $item->requiresConfirmation ? $item->attemptType : '',
            $delayedPstnWave,
        )));
    }
}
