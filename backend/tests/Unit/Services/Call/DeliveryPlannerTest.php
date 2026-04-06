<?php

namespace Tests\Unit\Services\Call;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\Tenant;
use App\Services\Call\DeliveryPlanner;
use App\Services\Call\EndpointCandidate;
use App\Services\Call\EndpointCandidateSet;
use App\Services\Call\ReachabilityDecision;
use App\Services\Call\ReachabilityDecisionSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeliveryPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telephony.call_delivery.wake_window_seconds' => 45,
            'telephony.call_delivery.pstn_delay_seconds' => 12,
        ]);
    }

    public function test_online_sip_candidates_are_planned_in_immediate_wave_without_waiting_for_push(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'acme.test']);
        $callSession = CallSession::factory()->for($tenant)->create();

        $sipCandidate = $this->candidate(
            bindingId: 'binding-sip',
            sourcePath: [['type' => 'extension', 'id' => 'ext-1']],
            priority: 0,
        );
        $pushCandidate = $this->candidate(
            bindingId: 'binding-push',
            candidateType: 'mobile_app',
            sipAor: 'sip:1001@acme.test',
            pushCapable: true,
            allowLateJoinAfterPush: true,
            sourcePath: [['type' => 'extension', 'id' => 'ext-1']],
            priority: 1,
        );

        $plan = app(DeliveryPlanner::class)->createDeliveryPlan(
            $callSession,
            new EndpointCandidateSet([$sipCandidate, $pushCandidate]),
            new ReachabilityDecisionSet([
                $this->decision('binding-sip', ReachabilityDecision::STATUS_ONLINE_SIP, canRingNow: true),
                $this->decision(
                    'binding-push',
                    ReachabilityDecision::STATUS_DORMANT_PUSH,
                    shouldSendPush: true,
                    lateJoinWindowUntil: '2025-01-01T10:00:45+00:00',
                ),
            ], ['wake_window_seconds' => 45]),
        );

        $this->assertSame(['binding-sip'], array_map(fn ($item) => $item->candidate->endpointBindingId, $plan->immediateSipWave));
        $this->assertSame(['binding-push'], array_map(fn ($item) => $item->candidate->endpointBindingId, $plan->immediatePushWave));
        $this->assertSame(CallDeliveryAttempt::TYPE_SIP, $plan->immediateSipWave[0]->attemptType);
        $this->assertSame(CallDeliveryAttempt::TYPE_PUSH, $plan->immediatePushWave[0]->attemptType);
        $this->assertSame([], $plan->delayedPstnWave);
    }

    public function test_dormant_push_candidates_capture_wake_window_and_late_join_metadata(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'mobile.test']);
        $callSession = CallSession::factory()->for($tenant)->create([
            'variables' => ['delivery_wake_window_seconds' => 90],
        ]);

        $pushCandidate = $this->candidate(
            bindingId: 'binding-mobile',
            candidateType: 'mobile_app',
            sipAor: 'sip:2001@mobile.test',
            pushCapable: true,
            allowLateJoinAfterPush: true,
            sourcePath: [['type' => 'ring_group', 'id' => 'rg-1']],
        );

        $plan = app(DeliveryPlanner::class)->createDeliveryPlan(
            $callSession,
            new EndpointCandidateSet([$pushCandidate]),
            new ReachabilityDecisionSet([
                $this->decision(
                    'binding-mobile',
                    ReachabilityDecision::STATUS_DORMANT_PUSH,
                    shouldSendPush: true,
                    lateJoinWindowUntil: '2025-01-01T11:01:30+00:00',
                ),
            ], ['wake_window_seconds' => 45]),
        );

        $this->assertSame(90, $plan->wakeWindowSeconds);
        $this->assertSame('2025-01-01T11:01:30+00:00', $plan->immediatePushWave[0]->lateJoinWindowUntil);
        $this->assertTrue($plan->immediatePushWave[0]->metadata['late_join_allowed']);
        $this->assertSame(CallDeliveryAttempt::TYPE_LATE_SIP, $plan->immediatePushWave[0]->metadata['late_join_attempt_type']);
        $this->assertSame('2025-01-01T11:01:30+00:00', $plan->wakeWindow['late_join_bindings']['binding-mobile']['late_join_window_until']);
    }

    public function test_pstn_candidates_are_planned_in_delayed_wave_with_confirmation_policy(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'pstn.test']);
        $callSession = CallSession::factory()->for($tenant)->create();

        $pstnCandidate = $this->candidate(
            bindingId: 'binding-pstn',
            candidateType: 'pstn_forward',
            sipAor: null,
            forwardNumber: '+15551234567',
            forwardRequiresConfirm: true,
            sourcePath: [['type' => 'agent', 'id' => 'agent-1']],
        );

        $plan = app(DeliveryPlanner::class)->createDeliveryPlan(
            $callSession,
            new EndpointCandidateSet([$pstnCandidate]),
            new ReachabilityDecisionSet([
                $this->decision(
                    'binding-pstn',
                    ReachabilityDecision::STATUS_PSTN_ELIGIBLE,
                    shouldOfferPstn: true,
                    decisionReason: 'pstn_forward_requires_confirmation',
                ),
            ]),
        );

        $this->assertSame(['binding-pstn'], array_map(fn ($item) => $item->candidate->endpointBindingId, $plan->delayedPstnWave));
        $this->assertSame(12, $plan->delayedPstnWave[0]->delaySeconds);
        $this->assertTrue($plan->delayedPstnWave[0]->requiresConfirmation);
        $this->assertSame([CallDeliveryAttempt::TYPE_PSTN], $plan->cancellationPolicy['pstn_confirmation_required_attempt_types']);
        $this->assertTrue($plan->cancellationPolicy['cancel_active_attempts_on_winner']);
    }

    public function test_planning_logic_is_consistent_across_route_origins(): void
    {
        Carbon::setTestNow('2025-01-01T10:00:00+00:00');

        $tenant = Tenant::factory()->create(['domain' => 'origin.test']);
        $callSession = CallSession::factory()->for($tenant)->create();

        $extensionSip = $this->candidate(
            bindingId: 'binding-ext-sip',
            sourcePath: [['type' => 'extension', 'id' => 'ext-1']],
            priority: 0,
        );
        $ringPush = $this->candidate(
            bindingId: 'binding-rg-push',
            candidateType: 'mobile_app',
            sipAor: 'sip:3002@origin.test',
            pushCapable: true,
            allowLateJoinAfterPush: true,
            sourcePath: [['type' => 'ring_group', 'id' => 'rg-1']],
            priority: 1,
        );
        $queuePstn = $this->candidate(
            bindingId: 'binding-queue-pstn',
            candidateType: 'pstn_forward',
            sipAor: null,
            forwardNumber: '+15557654321',
            forwardRequiresConfirm: true,
            sourcePath: [['type' => 'queue', 'id' => 'queue-1']],
            priority: 2,
        );

        $plan = app(DeliveryPlanner::class)->createDeliveryPlan(
            $callSession,
            new EndpointCandidateSet([
                $extensionSip,
                $ringPush,
                $queuePstn,
            ], ['resolved_candidate_count' => 3]),
            new ReachabilityDecisionSet([
                $this->decision('binding-ext-sip', ReachabilityDecision::STATUS_ONLINE_SIP, canRingNow: true),
                $this->decision(
                    'binding-rg-push',
                    ReachabilityDecision::STATUS_DORMANT_PUSH,
                    shouldSendPush: true,
                    lateJoinWindowUntil: '2025-01-01T10:00:45+00:00',
                ),
                $this->decision(
                    'binding-queue-pstn',
                    ReachabilityDecision::STATUS_PSTN_ELIGIBLE,
                    shouldOfferPstn: true,
                    decisionReason: 'pstn_forward_requires_confirmation',
                ),
            ], ['wake_window_seconds' => 45]),
        );

        $this->assertSame(['binding-ext-sip'], array_map(fn ($item) => $item->candidate->endpointBindingId, $plan->immediateSipWave));
        $this->assertSame(['binding-rg-push'], array_map(fn ($item) => $item->candidate->endpointBindingId, $plan->immediatePushWave));
        $this->assertSame(['binding-queue-pstn'], array_map(fn ($item) => $item->candidate->endpointBindingId, $plan->delayedPstnWave));
        $this->assertSame(['extension', 'ring_group', 'queue'], $plan->metadata['route_origins']);
        $this->assertSame(3, $plan->metadata['resolved_candidate_count']);

        Carbon::setTestNow();
    }

    public function test_planner_sorts_each_wave_by_candidate_priority_and_binding_id(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'sort.test']);
        $callSession = CallSession::factory()->for($tenant)->create();

        $slowSip = $this->candidate(bindingId: 'binding-z', priority: 5);
        $fastSip = $this->candidate(bindingId: 'binding-a', priority: 1);
        $samePriorityPushB = $this->candidate(
            bindingId: 'binding-push-b',
            candidateType: 'mobile_app',
            sipAor: 'sip:5002@sort.test',
            pushCapable: true,
            allowLateJoinAfterPush: true,
            priority: 2,
        );
        $samePriorityPushA = $this->candidate(
            bindingId: 'binding-push-a',
            candidateType: 'mobile_app',
            sipAor: 'sip:5001@sort.test',
            pushCapable: true,
            allowLateJoinAfterPush: true,
            priority: 2,
        );

        $plan = app(DeliveryPlanner::class)->createDeliveryPlan(
            $callSession,
            new EndpointCandidateSet([$slowSip, $samePriorityPushB, $fastSip, $samePriorityPushA]),
            new ReachabilityDecisionSet([
                $this->decision('binding-z', ReachabilityDecision::STATUS_ONLINE_SIP, canRingNow: true),
                $this->decision('binding-a', ReachabilityDecision::STATUS_ONLINE_SIP, canRingNow: true),
                $this->decision('binding-push-b', ReachabilityDecision::STATUS_DORMANT_PUSH, shouldSendPush: true),
                $this->decision('binding-push-a', ReachabilityDecision::STATUS_DORMANT_PUSH, shouldSendPush: true),
            ]),
        );

        $this->assertSame(['binding-a', 'binding-z'], array_map(fn ($item) => $item->candidate->endpointBindingId, $plan->immediateSipWave));
        $this->assertSame(['binding-push-a', 'binding-push-b'], array_map(fn ($item) => $item->candidate->endpointBindingId, $plan->immediatePushWave));
    }

    protected function candidate(
        string $bindingId,
        string $candidateType = 'desk_phone',
        ?string $sipAor = 'sip:1001@example.test',
        bool $pushCapable = false,
        bool $allowLateJoinAfterPush = false,
        ?string $forwardNumber = null,
        bool $forwardRequiresConfirm = false,
        array $sourcePath = [],
        int $priority = 0,
    ): EndpointCandidate {
        return new EndpointCandidate(
            endpointBindingId: $bindingId,
            ownerType: 'extension',
            ownerId: 'owner-'.$bindingId,
            candidateType: $candidateType,
            sipAor: $sipAor,
            pushCapable: $pushCapable,
            allowLateJoinAfterPush: $allowLateJoinAfterPush,
            forwardNumber: $forwardNumber,
            forwardRequiresConfirm: $forwardRequiresConfirm,
            priority: $priority,
            sourcePath: $sourcePath,
        );
    }

    protected function decision(
        string $bindingId,
        string $status,
        bool $canRingNow = false,
        bool $shouldSendPush = false,
        ?string $lateJoinWindowUntil = null,
        bool $shouldOfferPstn = false,
        string $decisionReason = 'test',
    ): ReachabilityDecision {
        return new ReachabilityDecision(
            endpointBindingId: $bindingId,
            status: $status,
            canRingNow: $canRingNow,
            shouldSendPush: $shouldSendPush,
            allowLateJoinWindowUntil: $lateJoinWindowUntil,
            shouldOfferPstn: $shouldOfferPstn,
            decisionReason: $decisionReason,
        );
    }
}
