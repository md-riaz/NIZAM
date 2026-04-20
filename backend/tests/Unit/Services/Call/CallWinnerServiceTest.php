<?php

namespace Tests\Unit\Services\Call;

use App\Events\CallDeliveryPushRequested;
use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\Organization;
use App\Services\Call\CallWinnerService;
use App\Services\Media\FreeSwitchCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class CallWinnerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_first_answer_wins_exactly_once_and_persists_winner_metadata(): void
    {
        Event::fake([CallDeliveryPushRequested::class]);
        $freeSwitch = $this->mockFreeSwitchCommandService();
        $freeSwitch->shouldReceive('execute')->once()->andReturn($this->killResponse('loser-leg'));

        [$callSession, $winningAttempt, $losingAttempt] = $this->makeAnsweredRaceAttempts();

        $result = app(CallWinnerService::class)->electWinner($callSession, $winningAttempt);

        $this->assertSame('winner_committed', $result['status']);

        $callSession->refresh();
        $winningAttempt->refresh();
        $losingAttempt->refresh();

        $this->assertSame($winningAttempt->id, data_get($callSession->variables, 'winner_attempt_id'));
        $this->assertSame($winningAttempt->freeswitch_leg_uuid, data_get($callSession->variables, 'winner_leg_uuid'));
        $this->assertSame(1, $callSession->lock_version);
        $this->assertSame('bridged', $callSession->state);
        $this->assertSame(CallDeliveryAttempt::STATUS_WON, $winningAttempt->status);
        $this->assertSame(CallDeliveryAttempt::STATUS_LOST, $losingAttempt->status);
        $this->assertSame('winner_already_committed', $losingAttempt->failure_reason);
    }

    public function test_duplicate_answer_race_keeps_existing_winner_and_marks_second_attempt_lost(): void
    {
        Event::fake([CallDeliveryPushRequested::class]);
        $freeSwitch = $this->mockFreeSwitchCommandService();
        $freeSwitch->shouldReceive('execute')->once()->andReturn($this->killResponse('loser-leg'));

        [$callSession, $firstAttempt, $secondAttempt] = $this->makeAnsweredRaceAttempts();

        app(CallWinnerService::class)->electWinner($callSession, $firstAttempt);
        $result = app(CallWinnerService::class)->electWinner($callSession->fresh(), $secondAttempt->fresh());

        $callSession->refresh();
        $firstAttempt->refresh();
        $secondAttempt->refresh();

        $this->assertSame('existing_winner', $result['status']);
        $this->assertSame($firstAttempt->id, $result['winner_attempt_id']);
        $this->assertSame($firstAttempt->id, data_get($callSession->variables, 'winner_attempt_id'));
        $this->assertSame(1, $callSession->lock_version);
        $this->assertSame(CallDeliveryAttempt::STATUS_WON, $firstAttempt->status);
        $this->assertSame(CallDeliveryAttempt::STATUS_LOST, $secondAttempt->status);
        $this->assertSame('winner_already_committed', $secondAttempt->failure_reason);
    }

    public function test_pstn_attempt_requires_confirmation_before_it_can_win(): void
    {
        Event::fake([CallDeliveryPushRequested::class]);
        $this->mockFreeSwitchCommandService();

        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $callSession = CallSession::factory()->for($organization)->create();
        $endpoint = EndpointBinding::factory()->for($organization)->create([
            'type' => EndpointBinding::TYPE_PSTN_FORWARD,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
            'forward_number' => '+15550009999',
            'forward_requires_confirm' => true,
        ]);

        $attempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($endpoint)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_PSTN,
                'status' => CallDeliveryAttempt::STATUS_ANSWERED,
                'freeswitch_leg_uuid' => 'pstn-leg',
                'metadata' => ['requires_confirmation' => true],
            ]);

        $result = app(CallWinnerService::class)->electWinner($callSession, $attempt);

        $callSession->refresh();
        $attempt->refresh();

        $this->assertSame('awaiting_confirmation', $result['status']);
        $this->assertNull(data_get($callSession->variables, 'winner_attempt_id'));
        $this->assertSame(0, $callSession->lock_version);
        $this->assertSame(CallDeliveryAttempt::STATUS_ANSWERED, $attempt->status);
        $this->assertSame('awaiting_confirmation', $attempt->failure_reason);
        $this->assertTrue((bool) data_get($attempt->metadata, 'awaiting_confirmation'));
    }

    public function test_winner_commit_terminalizes_pstn_and_push_losers_and_sends_answered_elsewhere(): void
    {
        Event::fake([CallDeliveryPushRequested::class]);
        $freeSwitch = $this->mockFreeSwitchCommandService();
        $freeSwitch->shouldReceive('execute')->times(2)->andReturnUsing(function (string $command, array $arguments) {
            return [
                'command' => $command,
                'arguments' => $arguments,
                'executed' => true,
                'response' => 'OK',
            ];
        });

        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $callSession = CallSession::factory()->for($organization)->create();
        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret',
            'directory_first_name' => 'Winner',
            'directory_last_name' => 'User',
            'voicemail_enabled' => true,
            'is_active' => true,
        ]);

        $winnerBinding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);
        $mobileBinding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'push_token' => 'push-token',
            'voip_push_token' => 'voip-token',
            'is_push_capable' => true,
        ]);
        $pstnBinding = EndpointBinding::factory()->forExtension($extension)->pstnForward()->create([
            'forward_number' => '+15551234567',
            'forward_requires_confirm' => true,
        ]);

        $winnerAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($winnerBinding)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
                'status' => CallDeliveryAttempt::STATUS_ANSWERED,
                'freeswitch_leg_uuid' => 'winner-leg',
            ]);

        $pushAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($mobileBinding)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_PUSH,
                'status' => CallDeliveryAttempt::STATUS_INITIATED,
                'metadata' => ['wave' => 'immediate_push'],
            ]);

        $pstnAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($pstnBinding)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_PSTN,
                'status' => CallDeliveryAttempt::STATUS_INITIATED,
                'freeswitch_leg_uuid' => 'pstn-leg',
                'metadata' => ['requires_confirmation' => true],
            ]);

        $result = app(CallWinnerService::class)->electWinner($callSession, $winnerAttempt);

        $callSession->refresh();
        $winnerAttempt->refresh();
        $pushAttempt->refresh();
        $pstnAttempt->refresh();

        $this->assertSame('winner_committed', $result['status']);
        $this->assertSame(CallDeliveryAttempt::STATUS_WON, $winnerAttempt->status);
        $this->assertSame(CallDeliveryAttempt::STATUS_CANCELLED, $pushAttempt->status);
        $this->assertSame('answered_elsewhere', $pushAttempt->failure_reason);
        $this->assertSame(CallDeliveryAttempt::STATUS_CANCELLED, $pstnAttempt->status);
        $this->assertSame('answered_elsewhere', $pstnAttempt->failure_reason);

        $this->assertDatabaseHas('push_notification_logs', [
            'call_session_id' => $callSession->id,
            'endpoint_binding_id' => $mobileBinding->id,
            'push_type' => 'answered_elsewhere',
            'status' => 'queued',
        ]);

        Event::assertDispatched(CallDeliveryPushRequested::class, function (CallDeliveryPushRequested $event) use ($callSession, $mobileBinding, $winnerAttempt): bool {
            return $event->callSessionId === $callSession->id
                && $event->endpointBindingId === $mobileBinding->id
                && data_get($event->payload, 'winner_attempt_id') === $winnerAttempt->id
                && data_get($event->payload, 'notification_type') === 'answered_elsewhere';
        });
    }

    /**
     * @return array{0:CallSession,1:CallDeliveryAttempt,2:CallDeliveryAttempt}
     */
    protected function makeAnsweredRaceAttempts(): array
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $extension = $organization->extensions()->create([
            'extension' => '1000',
            'password' => 'secret',
            'directory_first_name' => 'Race',
            'directory_last_name' => 'User',
            'voicemail_enabled' => true,
            'is_active' => true,
        ]);

        $callSession = CallSession::factory()->for($organization)->create();
        $winnerBinding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);
        $loserBinding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_SOFTPHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);

        $winnerAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($winnerBinding)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
                'status' => CallDeliveryAttempt::STATUS_ANSWERED,
                'freeswitch_leg_uuid' => 'winner-leg',
            ]);

        $loserAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($loserBinding)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
                'status' => CallDeliveryAttempt::STATUS_ANSWERED,
                'freeswitch_leg_uuid' => 'loser-leg',
            ]);

        return [$callSession, $winnerAttempt, $loserAttempt];
    }

    protected function mockFreeSwitchCommandService(): Mockery\MockInterface
    {
        return $this->mock(FreeSwitchCommandService::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function killResponse(string $legUuid): array
    {
        return [
            'command' => 'uuid_kill',
            'arguments' => [$legUuid, 'LOSE_RACE'],
            'executed' => true,
            'response' => 'OK',
        ];
    }
}
