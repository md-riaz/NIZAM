<?php

namespace Tests\Unit\Services\Call;

use App\Models\CallSession;
use App\Models\Organization;
use App\Services\Call\EndpointCandidate;
use App\Services\Call\EndpointCandidateSet;
use App\Services\Call\LiveRegistrationVisibility;
use App\Services\Call\ReachabilityCache;
use App\Services\Call\ReachabilityDecision;
use App\Services\Call\ReachabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReachabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'call_delivery.reachability.cache_store' => 'array',
            'call_delivery.reachability.cache_ttl_seconds' => 30,
            'call_delivery.reachability.wake_window_seconds' => 45,
        ]);
    }

    public function test_uses_fresh_cached_registration_without_hitting_live_visibility(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $callSession = CallSession::factory()->for($organization)->create();
        $candidate = $this->sipCandidate('binding-1', 'sip:1001@acme.test');
        $cache = new ReachabilityCache;
        $cache->markRegistered($organization->id, $candidate, ['source' => 'reachability_cache']);

        $visibility = $this->createMock(LiveRegistrationVisibility::class);
        $visibility->expects($this->never())->method('forOrganization');

        $resolver = new ReachabilityResolver($cache, $visibility);

        $resolved = $resolver->resolve($callSession->load('organization'), new EndpointCandidateSet([$candidate]));

        $decision = $resolved->decisions[0];

        $this->assertSame(ReachabilityDecision::STATUS_ONLINE_SIP, $decision->status);
        $this->assertTrue($decision->canRingNow);
        $this->assertFalse($decision->shouldSendPush);
        $this->assertSame('reachability_cache', $decision->decisionReason);
        $this->assertFalse($resolved->metadata['live_registration_fallback_used']);
    }

    public function test_stale_cache_falls_back_to_live_visibility_and_refreshes_cache(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $callSession = CallSession::factory()->for($organization)->create();
        $candidate = $this->sipCandidate('binding-2', 'sip:1002@acme.test');
        $cache = new ReachabilityCache;
        $cache->markUnregistered($organization->id, $candidate, ['source' => 'stale_seed'], now()->subMinutes(5));

        $visibility = $this->createMock(LiveRegistrationVisibility::class);
        $visibility->expects($this->once())
            ->method('forOrganization')
            ->with($this->callback(fn ($resolvedOrganization) => $resolvedOrganization instanceof Organization && $resolvedOrganization->id === $organization->id))
            ->willReturn([
                '1002' => [
                    'registered' => true,
                    'registration_user' => '1002',
                    'contact' => 'sip:1002@10.0.0.2:5060',
                    'user_agent' => 'Acme Softphone',
                    'network_ip' => '10.0.0.2',
                    'network_port' => '5060',
                    'source' => 'esl_live',
                ],
            ]);

        $resolver = new ReachabilityResolver($cache, $visibility);

        $resolved = $resolver->resolve($callSession->load('organization'), new EndpointCandidateSet([$candidate]));

        $decision = $resolved->decisions[0];
        $freshSnapshot = $cache->snapshotFor($organization->id, $candidate, 30);

        $this->assertSame(ReachabilityDecision::STATUS_ONLINE_SIP, $decision->status);
        $this->assertSame('esl_live', $decision->decisionReason);
        $this->assertSame('Acme Softphone', $decision->metadata['user_agent']);
        $this->assertTrue($resolved->metadata['live_registration_fallback_used']);
        $this->assertFalse($resolved->metadata['live_registration_fallback_unavailable']);
        $this->assertNotNull($freshSnapshot);
        $this->assertTrue($freshSnapshot['registered']);
        $this->assertSame('esl_live', $freshSnapshot['source']);
    }

    public function test_push_and_pstn_candidates_are_classified_when_registration_is_not_present(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $callSession = CallSession::factory()->for($organization)->create([
            'variables' => ['delivery_wake_window_seconds' => 90],
        ]);

        $pushCandidate = new EndpointCandidate(
            endpointBindingId: 'binding-3',
            ownerType: 'extension',
            ownerId: 'owner-3',
            candidateType: 'mobile_app',
            sipAor: 'sip:1003@acme.test',
            pushCapable: true,
            allowLateJoinAfterPush: true,
            forwardNumber: null,
            forwardRequiresConfirm: false,
            priority: 0,
        );
        $pstnCandidate = new EndpointCandidate(
            endpointBindingId: 'binding-4',
            ownerType: 'extension',
            ownerId: 'owner-4',
            candidateType: 'pstn_forward',
            sipAor: null,
            pushCapable: false,
            allowLateJoinAfterPush: false,
            forwardNumber: '+15551234567',
            forwardRequiresConfirm: true,
            priority: 1,
        );

        $visibility = $this->createMock(LiveRegistrationVisibility::class);
        $visibility->expects($this->once())
            ->method('forOrganization')
            ->with($this->callback(fn ($resolvedOrganization) => $resolvedOrganization instanceof Organization && $resolvedOrganization->id === $organization->id))
            ->willReturn([]);

        $resolver = new ReachabilityResolver(new ReachabilityCache, $visibility);

        $resolved = $resolver->resolve($callSession->load('organization'), new EndpointCandidateSet([$pushCandidate, $pstnCandidate]));

        $pushDecision = $resolved->decisions[0];
        $pstnDecision = $resolved->decisions[1];

        $this->assertSame(ReachabilityDecision::STATUS_DORMANT_PUSH, $pushDecision->status);
        $this->assertFalse($pushDecision->canRingNow);
        $this->assertTrue($pushDecision->shouldSendPush);
        $this->assertNotNull($pushDecision->allowLateJoinWindowUntil);
        $this->assertSame('not_registered_push_capable', $pushDecision->decisionReason);

        $this->assertSame(ReachabilityDecision::STATUS_PSTN_ELIGIBLE, $pstnDecision->status);
        $this->assertTrue($pstnDecision->shouldOfferPstn);
        $this->assertSame('pstn_forward_requires_confirmation', $pstnDecision->decisionReason);
    }

    public function test_cache_store_failures_degrade_to_live_visibility_fallback(): void
    {
        config(['call_delivery.reachability.cache_store' => 'missing-store']);

        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $callSession = CallSession::factory()->for($organization)->create();
        $candidate = $this->sipCandidate('binding-5', 'sip:1005@acme.test');

        $visibility = $this->createMock(LiveRegistrationVisibility::class);
        $visibility->expects($this->once())
            ->method('forOrganization')
            ->with($this->callback(fn ($resolvedOrganization) => $resolvedOrganization instanceof Organization && $resolvedOrganization->id === $organization->id))
            ->willReturn([
                '1005' => [
                    'registered' => true,
                    'registration_user' => '1005',
                    'source' => 'esl_live',
                ],
            ]);

        $resolver = new ReachabilityResolver(new ReachabilityCache, $visibility);

        $resolved = $resolver->resolve($callSession->load('organization'), new EndpointCandidateSet([$candidate]));

        $decision = $resolved->decisions[0];

        $this->assertSame(ReachabilityDecision::STATUS_ONLINE_SIP, $decision->status);
        $this->assertSame('esl_live', $decision->decisionReason);
        $this->assertTrue($resolved->metadata['live_registration_fallback_used']);
    }

    public function test_live_visibility_unavailable_marks_push_candidates_with_degraded_reason(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $callSession = CallSession::factory()->for($organization)->create();
        $candidate = new EndpointCandidate(
            endpointBindingId: 'binding-6',
            ownerType: 'extension',
            ownerId: 'owner-6',
            candidateType: 'mobile_app',
            sipAor: 'sip:1006@acme.test',
            pushCapable: true,
            allowLateJoinAfterPush: true,
            forwardNumber: null,
            forwardRequiresConfirm: false,
            priority: 0,
        );

        $visibility = $this->createMock(LiveRegistrationVisibility::class);
        $visibility->expects($this->once())
            ->method('forOrganization')
            ->with($this->callback(fn ($resolvedOrganization) => $resolvedOrganization instanceof Organization && $resolvedOrganization->id === $organization->id))
            ->willReturn(null);

        $resolver = new ReachabilityResolver(new ReachabilityCache, $visibility);

        $resolved = $resolver->resolve($callSession->load('organization'), new EndpointCandidateSet([$candidate]));

        $decision = $resolved->decisions[0];

        $this->assertSame(ReachabilityDecision::STATUS_DORMANT_PUSH, $decision->status);
        $this->assertTrue($decision->shouldSendPush);
        $this->assertSame('registration_visibility_unavailable_push_fallback', $decision->decisionReason);
        $this->assertTrue($resolved->metadata['live_registration_fallback_used']);
        $this->assertTrue($resolved->metadata['live_registration_fallback_unavailable']);
    }

    protected function sipCandidate(string $bindingId, string $sipAor): EndpointCandidate
    {
        return new EndpointCandidate(
            endpointBindingId: $bindingId,
            ownerType: 'extension',
            ownerId: 'owner-'.$bindingId,
            candidateType: 'desk_phone',
            sipAor: $sipAor,
            pushCapable: false,
            allowLateJoinAfterPush: false,
            forwardNumber: null,
            forwardRequiresConfirm: false,
            priority: 0,
        );
    }
}
