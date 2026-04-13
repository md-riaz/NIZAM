<?php

namespace Tests\Feature\Api;

use App\Models\CallDeliveryAttempt;
use App\Models\CallEventLog;
use App\Models\CallSession;
use App\Models\CallTraceEvent;
use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InteractionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_show_returns_tenant_scoped_interaction_overview(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $endpoint = EndpointBinding::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => EndpointBinding::PLATFORM_IOS,
        ]);

        $callSession = CallSession::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => 'call-123',
            'state' => 'bridged',
            'started_at' => Carbon::parse('2026-04-12 10:00:00'),
            'ended_at' => Carbon::parse('2026-04-12 10:02:00'),
            'variables' => [
                'winner_leg_uuid' => 'leg-123',
            ],
        ]);

        CallEventLog::query()->create([
            'call_session_id' => $callSession->id,
            'tenant_id' => $tenant->id,
            'call_uuid' => $callSession->call_uuid,
            'event_id' => 'event-1',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'source' => 'switch',
            'payload' => ['direction' => 'inbound'],
            'occurred_at' => Carbon::parse('2026-04-12 10:00:00'),
            'received_at' => Carbon::parse('2026-04-12 10:00:00'),
        ]);

        CallTraceEvent::query()->create([
            'call_session_id' => $callSession->id,
            'call_uuid' => $callSession->call_uuid,
            'node_id' => 'node-1',
            'node_type' => 'ring_group',
            'action' => 'flow.node.executing',
            'payload' => ['node_label' => 'Support queue'],
            'occurred_at' => Carbon::parse('2026-04-12 10:00:05'),
        ]);

        $winningAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($endpoint)
            ->won()
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_PUSH,
                'started_at' => Carbon::parse('2026-04-12 10:00:10'),
                'answered_at' => Carbon::parse('2026-04-12 10:00:25'),
                'ended_at' => Carbon::parse('2026-04-12 10:02:00'),
                'metadata' => ['wave' => 'mobile'],
            ]);

        PushNotificationLog::factory()->create([
            'call_session_id' => $callSession->id,
            'endpoint_binding_id' => $endpoint->id,
            'push_type' => 'wake',
            'status' => 'sent',
            'sent_at' => Carbon::parse('2026-04-12 10:00:08'),
            'response_payload' => ['provider' => 'fcm'],
        ]);

        $callSession->forceFill([
            'variables' => [
                'winner_attempt_id' => $winningAttempt->id,
                'winner_leg_uuid' => 'leg-123',
                'winner_committed_at' => Carbon::parse('2026-04-12 10:00:25')->toIso8601String(),
            ],
        ])->save();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}/interactions/{$callSession->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $callSession->id)
            ->assertJsonPath('data.call_uuid', 'call-123')
            ->assertJsonPath('data.summary.status_label', 'Bridged')
            ->assertJsonPath('data.summary.outcome_label', 'Answered via push to ios mobile app')
            ->assertJsonPath('data.summary.delivery_attempt_count', 1)
            ->assertJsonPath('data.summary.push_notification_count', 1)
            ->assertJsonPath('data.summary.call_event_count', 1)
            ->assertJsonPath('data.summary.trace_event_count', 1)
            ->assertJsonPath('data.summary.timeline_event_count', 4)
            ->assertJsonPath('data.timeline.0.type', 'call.created')
            ->assertJsonPath('data.timeline.3.type', 'delivery.push.won')
            ->assertJsonPath('data.winning_attempt.attempt_id', $winningAttempt->id)
            ->assertJsonPath('data.winning_attempt.leg_uuid', 'leg-123')
            ->assertJsonPath('data.winning_attempt.attempt.id', $winningAttempt->id)
            ->assertJsonPath('data.delivery_attempts.0.endpoint.id', $endpoint->id)
            ->assertJsonPath('data.push_notification_logs.0.endpoint.id', $endpoint->id)
            ->assertJsonPath('data.trace_analysis.errors', []);
    }

    public function test_show_returns_not_found_for_call_session_from_another_tenant(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'acme.test']);
        $otherTenant = Tenant::factory()->create(['domain' => 'other.test']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $callSession = CallSession::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}/interactions/{$callSession->id}")
            ->assertNotFound()
            ->assertJson([
                'message' => 'Interaction not found.',
            ]);
    }
}
