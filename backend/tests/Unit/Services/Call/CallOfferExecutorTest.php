<?php

namespace Tests\Unit\Services\Call;

use App\Events\CallDeliveryPushRequested;
use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\Organization;
use App\Services\Call\CallOfferExecutor;
use App\Services\Call\DeliveryPlan;
use App\Services\Call\DeliveryPlanItem;
use App\Services\Call\EndpointCandidate;
use App\Services\Call\OfferCommandDispatcher;
use App\Services\Call\OfferCommandResult;
use App\Services\Call\ReachabilityDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallOfferExecutorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_execute_plan_persists_sip_and_pstn_attempts_and_push_logs(): void
    {
        Event::fake([CallDeliveryPushRequested::class]);

        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $callSession = CallSession::factory()->for($organization)->create([
            'variables' => [
                'caller_id_name' => 'Alice',
                'caller_id_number' => '+15550001111',
            ],
        ]);

        $sipBinding = EndpointBinding::factory()->for($organization)->forExtension(
            $organization->extensions()->create([
                'extension' => '1001',
                'password' => 'secret',
                'first_name' => 'Desk',
                'last_name' => 'Phone',
                'voicemail_enabled' => true,
                'is_active' => true,
            ])
        )->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
            'allow_late_join_after_push' => false,
        ]);

        $pushBinding = EndpointBinding::factory()->forExtension($sipBinding->extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'push_token' => 'push-token',
            'is_push_capable' => true,
            'allow_late_join_after_push' => true,
        ]);

        $pstnBinding = EndpointBinding::factory()->forExtension($sipBinding->extension)->pstnForward()->create([
            'forward_number' => '+15557654321',
            'forward_requires_confirm' => true,
        ]);

        $dispatcher = new FakeOfferCommandDispatcher([
            $sipBinding->id => OfferCommandResult::success('sip queued', 'sip-leg-uuid'),
            $pstnBinding->id => OfferCommandResult::success('pstn queued', 'pstn-leg-uuid'),
        ]);

        $this->app->instance(OfferCommandDispatcher::class, $dispatcher);

        $plan = new DeliveryPlan(
            callSessionId: $callSession->id,
            wakeWindowSeconds: 45,
            immediateSipWave: [
                $this->planItem($sipBinding->id, CallDeliveryAttempt::TYPE_SIP, 'immediate_sip', sipAor: 'sip:1001@acme.test'),
            ],
            immediatePushWave: [
                $this->planItem(
                    $pushBinding->id,
                    CallDeliveryAttempt::TYPE_PUSH,
                    'immediate_push',
                    candidateType: 'mobile_app',
                    sipAor: 'sip:1001@acme.test',
                    pushCapable: true,
                    allowLateJoinAfterPush: true,
                    lateJoinWindowUntil: '2025-01-01T10:00:45+00:00',
                ),
            ],
            delayedPstnWave: [
                $this->planItem(
                    $pstnBinding->id,
                    CallDeliveryAttempt::TYPE_PSTN,
                    'delayed_pstn',
                    candidateType: 'pstn_forward',
                    sipAor: null,
                    forwardNumber: '+15557654321',
                    requiresConfirmation: true,
                    delaySeconds: 12,
                ),
            ],
        );

        $result = app(CallOfferExecutor::class)->executePlan($plan, [
            'caller_leg_uuid' => 'caller-leg-uuid',
            'caller_id_name' => 'Alice',
            'caller_id_number' => '+15550001111',
            'pstn_gateway' => 'gw-primary',
        ]);

        $this->assertCount(1, $result['sip_attempt_ids']);
        $this->assertCount(1, $result['push_attempt_ids']);
        $this->assertCount(1, $result['pstn_attempt_ids']);

        $sipAttempt = CallDeliveryAttempt::query()->whereKey($result['sip_attempt_ids'][0])->firstOrFail();
        $pushAttempt = CallDeliveryAttempt::query()->whereKey($result['push_attempt_ids'][0])->firstOrFail();
        $pstnAttempt = CallDeliveryAttempt::query()->whereKey($result['pstn_attempt_ids'][0])->firstOrFail();

        $this->assertSame(CallDeliveryAttempt::STATUS_INITIATED, $sipAttempt->status);
        $this->assertSame('sip-leg-uuid', $sipAttempt->freeswitch_leg_uuid);
        $this->assertSame('immediate_sip', $sipAttempt->metadata['wave']);

        $this->assertSame(CallDeliveryAttempt::STATUS_INITIATED, $pushAttempt->status);
        $this->assertArrayHasKey('push_notification_log_id', $pushAttempt->metadata);
        $this->assertSame('queued', $pushAttempt->metadata['push_status']);

        $this->assertSame(CallDeliveryAttempt::STATUS_INITIATED, $pstnAttempt->status);
        $this->assertSame('pstn-leg-uuid', $pstnAttempt->freeswitch_leg_uuid);
        $this->assertTrue($pstnAttempt->metadata['requires_confirmation']);
        $this->assertSame(12, $pstnAttempt->metadata['delay_seconds']);

        $this->assertDatabaseHas('push_notification_logs', [
            'call_session_id' => $callSession->id,
            'endpoint_binding_id' => $pushBinding->id,
            'push_type' => 'wake',
            'status' => 'queued',
        ]);

        Event::assertDispatched(CallDeliveryPushRequested::class, function (CallDeliveryPushRequested $event) use ($callSession, $pushBinding): bool {
            return $event->callSessionId === $callSession->id
                && $event->endpointBindingId === $pushBinding->id;
        });
    }

    public function test_execute_plan_is_idempotent_for_existing_active_attempts_and_push_logs(): void
    {
        Event::fake([CallDeliveryPushRequested::class]);

        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $extension = $organization->extensions()->create([
            'extension' => '1002',
            'password' => 'secret',
            'first_name' => 'Repeat',
            'last_name' => 'Target',
            'voicemail_enabled' => true,
            'is_active' => true,
        ]);
        $callSession = CallSession::factory()->for($organization)->create();
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'push_token' => 'push-token',
            'is_push_capable' => true,
        ]);

        $existingAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($binding)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_PUSH,
                'status' => CallDeliveryAttempt::STATUS_INITIATED,
                'started_at' => now(),
                'metadata' => ['existing' => true],
            ]);

        $callSession->pushNotificationLogs()->create([
            'endpoint_binding_id' => $binding->id,
            'push_type' => 'wake',
            'provider_message_id' => 'existing-message',
            'status' => 'queued',
            'sent_at' => now(),
            'response_payload' => ['existing' => true],
        ]);

        $dispatcher = new FakeOfferCommandDispatcher([]);
        $this->app->instance(OfferCommandDispatcher::class, $dispatcher);

        $plan = new DeliveryPlan(
            callSessionId: $callSession->id,
            wakeWindowSeconds: 45,
            immediatePushWave: [
                $this->planItem(
                    $binding->id,
                    CallDeliveryAttempt::TYPE_PUSH,
                    'immediate_push',
                    candidateType: 'mobile_app',
                    sipAor: 'sip:1002@acme.test',
                    pushCapable: true,
                    allowLateJoinAfterPush: true,
                    lateJoinWindowUntil: '2025-01-01T10:00:45+00:00',
                ),
            ],
        );

        $result = app(CallOfferExecutor::class)->executePlan($plan);

        $this->assertSame([$existingAttempt->id], $result['push_attempt_ids']);
        $this->assertSame(1, CallDeliveryAttempt::query()->count());
        $this->assertSame(1, $callSession->pushNotificationLogs()->count());
        $this->assertSame([], $dispatcher->sipCalls);
        $this->assertSame([], $dispatcher->pstnCalls);
        Event::assertNotDispatched(CallDeliveryPushRequested::class);
    }

    public function test_offer_failures_are_persisted_as_failed_attempts(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $extension = $organization->extensions()->create([
            'extension' => '1003',
            'password' => 'secret',
            'first_name' => 'Fail',
            'last_name' => 'Case',
            'voicemail_enabled' => true,
            'is_active' => true,
        ]);
        $callSession = CallSession::factory()->for($organization)->create();
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);

        $dispatcher = new FakeOfferCommandDispatcher([
            $binding->id => OfferCommandResult::failure('esl unavailable', 'error-response'),
        ]);
        $this->app->instance(OfferCommandDispatcher::class, $dispatcher);

        $plan = new DeliveryPlan(
            callSessionId: $callSession->id,
            wakeWindowSeconds: 45,
            immediateSipWave: [
                $this->planItem($binding->id, CallDeliveryAttempt::TYPE_SIP, 'immediate_sip', sipAor: 'sip:1003@acme.test'),
            ],
        );

        $result = app(CallOfferExecutor::class)->executePlan($plan);
        $attempt = CallDeliveryAttempt::query()->whereKey($result['sip_attempt_ids'][0])->firstOrFail();

        $this->assertSame(CallDeliveryAttempt::STATUS_FAILED, $attempt->status);
        $this->assertSame('esl unavailable', $attempt->failure_reason);
        $this->assertNotNull($attempt->ended_at);
    }

    protected function planItem(
        string $endpointBindingId,
        string $attemptType,
        string $wave,
        string $candidateType = 'desk_phone',
        ?string $sipAor = 'sip:1001@acme.test',
        bool $pushCapable = false,
        bool $allowLateJoinAfterPush = false,
        ?string $forwardNumber = null,
        bool $requiresConfirmation = false,
        int $delaySeconds = 0,
        ?string $lateJoinWindowUntil = null,
    ): DeliveryPlanItem {
        return new DeliveryPlanItem(
            candidate: new EndpointCandidate(
                endpointBindingId: $endpointBindingId,
                ownerType: 'extension',
                ownerId: 'owner-'.$endpointBindingId,
                candidateType: $candidateType,
                sipAor: $sipAor,
                pushCapable: $pushCapable,
                allowLateJoinAfterPush: $allowLateJoinAfterPush,
                forwardNumber: $forwardNumber,
                forwardRequiresConfirm: $requiresConfirmation,
                priority: 0,
                sourcePath: [['type' => 'extension', 'id' => 'ext-1']],
            ),
            decision: new ReachabilityDecision(
                endpointBindingId: $endpointBindingId,
                status: ReachabilityDecision::STATUS_ONLINE_SIP,
                canRingNow: $attemptType === CallDeliveryAttempt::TYPE_SIP,
                shouldSendPush: $attemptType === CallDeliveryAttempt::TYPE_PUSH,
                allowLateJoinWindowUntil: $lateJoinWindowUntil,
                shouldOfferPstn: $attemptType === CallDeliveryAttempt::TYPE_PSTN,
                decisionReason: 'test',
            ),
            wave: $wave,
            attemptType: $attemptType,
            delaySeconds: $delaySeconds,
            requiresConfirmation: $requiresConfirmation,
            lateJoinWindowUntil: $lateJoinWindowUntil,
            metadata: ['planned_by' => 'test'],
        );
    }
}

class FakeOfferCommandDispatcher implements OfferCommandDispatcher
{
    /** @var array<string, OfferCommandResult> */
    public array $results;

    /** @var list<string> */
    public array $sipCalls = [];

    /** @var list<string> */
    public array $pstnCalls = [];

    /**
     * @param  array<string, OfferCommandResult>  $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function originateSip(DeliveryPlanItem $item, array $context = []): OfferCommandResult
    {
        $this->sipCalls[] = $item->candidate->endpointBindingId;

        return $this->results[$item->candidate->endpointBindingId]
            ?? OfferCommandResult::success('sip-ok', 'sip-'.$item->candidate->endpointBindingId);
    }

    public function originatePstn(DeliveryPlanItem $item, array $context = []): OfferCommandResult
    {
        $this->pstnCalls[] = $item->candidate->endpointBindingId;

        return $this->results[$item->candidate->endpointBindingId]
            ?? OfferCommandResult::success('pstn-ok', 'pstn-'.$item->candidate->endpointBindingId);
    }
}
