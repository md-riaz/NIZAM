<?php

namespace Tests\Feature\Api;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\CallTraceEvent;
use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallSessionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_show_includes_delivery_attempt_winner_and_push_metadata(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        $endpoint = EndpointBinding::factory()->create(['organization_id' => $organization->id]);
        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'winner_attempt_id' => null,
                'winner_leg_uuid' => 'winner-leg-from-session',
                'winner_committed_at' => now()->toIso8601String(),
            ],
        ]);

        $losingAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($endpoint)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_PUSH,
                'status' => CallDeliveryAttempt::STATUS_CANCELLED,
                'metadata' => ['wave' => 'push'],
                'failure_reason' => 'answered_elsewhere',
            ]);

        $winningAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($endpoint)
            ->won()
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_LATE_SIP,
                'freeswitch_leg_uuid' => 'winner-leg-uuid',
                'metadata' => ['wave' => 'late_join'],
            ]);

        $callSession->forceFill([
            'variables' => [
                'winner_attempt_id' => $winningAttempt->id,
                'winner_leg_uuid' => 'winner-leg-from-session',
                'winner_committed_at' => now()->toIso8601String(),
            ],
        ])->save();

        PushNotificationLog::factory()->create([
            'call_session_id' => $callSession->id,
            'endpoint_binding_id' => $endpoint->id,
            'status' => 'cancelled',
            'response_payload' => ['reason' => 'winner_committed'],
        ]);

        CallTraceEvent::query()->create([
            'call_session_id' => $callSession->id,
            'call_uuid' => $callSession->call_uuid,
            'node_id' => 'node-1',
            'node_type' => 'ring_team',
            'action' => 'delivery.plan.created',
            'payload' => ['attempt_count' => 2],
            'occurred_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/calls/{$callSession->id}");

        $response->assertOk()
            ->assertJsonPath('data.winner.attempt_id', $winningAttempt->id)
            ->assertJsonPath('data.winner.leg_uuid', 'winner-leg-from-session')
            ->assertJsonPath('data.winner.attempt.id', $winningAttempt->id)
            ->assertJsonPath('data.winner.attempt.endpoint.id', $endpoint->id)
            ->assertJsonCount(2, 'data.delivery_attempts')
            ->assertJsonPath('data.delivery_attempts.0.id', $losingAttempt->id)
            ->assertJsonPath('data.delivery_attempts.1.id', $winningAttempt->id)
            ->assertJsonPath('data.delivery_attempts.1.endpoint.type', $endpoint->type)
            ->assertJsonCount(1, 'data.push_notification_logs')
            ->assertJsonPath('data.push_notification_logs.0.endpoint.id', $endpoint->id)
            ->assertJsonPath('data.push_notification_logs.0.response_payload.reason', 'winner_committed');
    }

    public function test_analyze_includes_delivery_attempt_winner_and_push_observability(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        $endpoint = EndpointBinding::factory()->create(['organization_id' => $organization->id]);
        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'variables' => [
                'winner_leg_uuid' => 'session-leg-uuid',
            ],
        ]);

        CallTraceEvent::query()->create([
            'call_session_id' => $callSession->id,
            'call_uuid' => $callSession->call_uuid,
            'node_id' => 'node-1',
            'node_type' => 'extension',
            'action' => 'flow.node.executing',
            'payload' => ['step' => 1],
            'occurred_at' => now()->subSeconds(2),
        ]);

        CallTraceEvent::query()->create([
            'call_session_id' => $callSession->id,
            'call_uuid' => $callSession->call_uuid,
            'node_id' => 'node-1',
            'node_type' => 'extension',
            'action' => 'flow.node.executed',
            'payload' => ['step' => 2],
            'occurred_at' => now(),
        ]);

        $winningAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($endpoint)
            ->won()
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
                'freeswitch_leg_uuid' => 'winning-leg-uuid',
                'metadata' => ['wave' => 'immediate'],
            ]);

        PushNotificationLog::factory()->create([
            'call_session_id' => $callSession->id,
            'endpoint_binding_id' => $endpoint->id,
            'status' => 'suppressed',
            'response_payload' => ['reason' => 'winner_exists'],
        ]);

        $callSession->forceFill([
            'variables' => [
                'winner_attempt_id' => $winningAttempt->id,
                'winner_leg_uuid' => 'session-leg-uuid',
                'winner_committed_at' => now()->toIso8601String(),
            ],
        ])->save();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/calls/{$callSession->id}/analyze");

        $response->assertOk()
            ->assertJsonPath('data.winner.attempt_id', $winningAttempt->id)
            ->assertJsonPath('data.winner.leg_uuid', 'session-leg-uuid')
            ->assertJsonPath('data.winner.attempt.id', $winningAttempt->id)
            ->assertJsonCount(1, 'data.delivery_attempts')
            ->assertJsonPath('data.delivery_attempts.0.endpoint.id', $endpoint->id)
            ->assertJsonCount(1, 'data.push_notification_logs')
            ->assertJsonPath('data.push_notification_logs.0.response_payload.reason', 'winner_exists')
            ->assertJsonCount(2, 'data.timeline');
    }
}
