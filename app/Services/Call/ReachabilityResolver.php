<?php

namespace App\Services\Call;

use App\Models\CallSession;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

class ReachabilityResolver
{
    public function __construct(
        protected ReachabilityCache $cache,
        protected LiveRegistrationVisibility $liveRegistrationVisibility,
    ) {}

    public function resolve(CallSession $callSession, EndpointCandidateSet $candidateSet): ReachabilityDecisionSet
    {
        $tenant = $callSession->relationLoaded('tenant') && $callSession->tenant instanceof Tenant
            ? $callSession->tenant
            : $callSession->tenant()->firstOrFail();
        $now = CarbonImmutable::now();
        $cacheTtlSeconds = max(1, (int) config('call_delivery.reachability.cache_ttl_seconds', 30));
        $liveFallbackRequired = false;
        $cachedSnapshots = [];
        $sipCandidatesMissingFreshState = [];

        foreach ($candidateSet->candidates as $candidate) {
            if (blank($candidate->sipAor)) {
                continue;
            }

            $snapshot = $this->cache->snapshotFor($tenant->id, $candidate, $cacheTtlSeconds);

            if ($snapshot !== null) {
                $cachedSnapshots[$candidate->endpointBindingId] = $snapshot;

                continue;
            }

            $sipCandidatesMissingFreshState[] = $candidate;
            $liveFallbackRequired = true;
        }

        $liveRegistrations = [];
        $liveFallbackUsed = false;
        $liveFallbackUnavailable = false;

        if ($sipCandidatesMissingFreshState !== []) {
            $liveFallbackUsed = true;
            $liveVisibility = $this->liveRegistrationVisibility->forTenant($tenant);
            $liveFallbackUnavailable = $liveVisibility === null;
            $liveRegistrations = $liveVisibility ?? [];

            if (! $liveFallbackUnavailable) {
                $this->cache->rememberCandidateSnapshots($tenant->id, $sipCandidatesMissingFreshState, $liveRegistrations, $now);
            }
        }

        $decisions = [];

        foreach ($candidateSet->candidates as $candidate) {
            $snapshot = $cachedSnapshots[$candidate->endpointBindingId] ?? $this->snapshotFromLiveRegistrations($candidate, $liveRegistrations);
            $decisions[] = $this->classifyCandidate($candidate, $snapshot, $now, $liveFallbackUnavailable);
        }

        return new ReachabilityDecisionSet(
            decisions: $decisions,
            metadata: [
                ...$candidateSet->metadata,
                'cache_ttl_seconds' => $cacheTtlSeconds,
                'wake_window_seconds' => $this->wakeWindowSeconds($callSession),
                'live_registration_fallback_used' => $liveFallbackUsed,
                'live_registration_fallback_unavailable' => $liveFallbackUnavailable,
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    protected function classifyCandidate(
        EndpointCandidate $candidate,
        ?array $snapshot,
        CarbonImmutable $now,
        bool $liveFallbackUnavailable,
    ): ReachabilityDecision {
        if ($candidate->forwardNumber !== null) {
            return new ReachabilityDecision(
                endpointBindingId: $candidate->endpointBindingId,
                status: ReachabilityDecision::STATUS_PSTN_ELIGIBLE,
                canRingNow: false,
                shouldSendPush: false,
                allowLateJoinWindowUntil: null,
                shouldOfferPstn: true,
                decisionReason: $candidate->forwardRequiresConfirm
                    ? 'pstn_forward_requires_confirmation'
                    : 'pstn_forward_available',
                metadata: [
                    'candidate_type' => $candidate->candidateType,
                    'forward_number' => $candidate->forwardNumber,
                    'forward_requires_confirm' => $candidate->forwardRequiresConfirm,
                ],
            );
        }

        if ((bool) data_get($snapshot, 'registered', false)) {
            return new ReachabilityDecision(
                endpointBindingId: $candidate->endpointBindingId,
                status: ReachabilityDecision::STATUS_ONLINE_SIP,
                canRingNow: true,
                shouldSendPush: false,
                allowLateJoinWindowUntil: null,
                shouldOfferPstn: false,
                decisionReason: (string) data_get($snapshot, 'source', 'reachability_cache'),
                metadata: [
                    'sip_aor' => $candidate->sipAor,
                    'registration_user' => data_get($snapshot, 'registration_user'),
                    'contact' => data_get($snapshot, 'contact'),
                    'user_agent' => data_get($snapshot, 'user_agent'),
                    'network_ip' => data_get($snapshot, 'network_ip'),
                    'network_port' => data_get($snapshot, 'network_port'),
                    'observed_at' => data_get($snapshot, 'observed_at'),
                ],
            );
        }

        if ($candidate->pushCapable) {
            return new ReachabilityDecision(
                endpointBindingId: $candidate->endpointBindingId,
                status: ReachabilityDecision::STATUS_DORMANT_PUSH,
                canRingNow: false,
                shouldSendPush: true,
                allowLateJoinWindowUntil: $candidate->allowLateJoinAfterPush
                    ? $now->addSeconds($this->wakeWindowSeconds())->toIso8601String()
                    : null,
                shouldOfferPstn: false,
                decisionReason: $liveFallbackUnavailable
                    ? 'registration_visibility_unavailable_push_fallback'
                    : 'not_registered_push_capable',
                metadata: [
                    'sip_aor' => $candidate->sipAor,
                    'registration_user' => data_get($snapshot, 'registration_user'),
                    'observed_at' => data_get($snapshot, 'observed_at'),
                ],
            );
        }

        return new ReachabilityDecision(
            endpointBindingId: $candidate->endpointBindingId,
            status: ReachabilityDecision::STATUS_UNAVAILABLE,
            canRingNow: false,
            shouldSendPush: false,
            allowLateJoinWindowUntil: null,
            shouldOfferPstn: false,
            decisionReason: $liveFallbackUnavailable
                ? 'registration_visibility_unavailable'
                : 'candidate_unreachable',
            metadata: [
                'sip_aor' => $candidate->sipAor,
                'registration_user' => data_get($snapshot, 'registration_user'),
                'observed_at' => data_get($snapshot, 'observed_at'),
            ],
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $liveRegistrations
     * @return array<string, mixed>|null
     */
    protected function snapshotFromLiveRegistrations(EndpointCandidate $candidate, array $liveRegistrations): ?array
    {
        if (blank($candidate->sipAor) || ! preg_match('/^sip:([^@]+)@/i', $candidate->sipAor, $matches)) {
            return null;
        }

        return $liveRegistrations[strtolower($matches[1])] ?? [
            'registered' => false,
            'registration_user' => strtolower($matches[1]),
            'source' => 'esl_live',
        ];
    }

    protected function wakeWindowSeconds(?CallSession $callSession = null): int
    {
        $sessionOverride = $callSession
            ? data_get($callSession->variables, 'delivery_wake_window_seconds')
            : null;

        if (is_numeric($sessionOverride)) {
            return max(1, (int) $sessionOverride);
        }

        return max(1, (int) config('call_delivery.reachability.wake_window_seconds', 30));
    }
}
