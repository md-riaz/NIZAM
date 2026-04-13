<?php

namespace Tests\Unit\Services\Interaction;

use App\Models\CallDeliveryAttempt;
use App\Models\CallEventLog;
use App\Models\CallSession;
use App\Models\CallTraceEvent;
use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use App\Models\Tenant;
use App\Services\Interaction\InteractionOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class InteractionOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_it_builds_business_readable_interaction_overview(): void
    {
        $tenant = Tenant::factory()->create();
        $endpoint = EndpointBinding::factory()->create(['tenant_id' => $tenant->id]);

        $session = CallSession::factory()->create([
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
            'call_session_id' => $session->id,
            'tenant_id' => $tenant->id,
            'call_uuid' => $session->call_uuid,
            'event_id' => 'event-1',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
            'source' => 'switch',
            'payload' => ['direction' => 'inbound'],
            'occurred_at' => Carbon::parse('2026-04-12 10:00:00'),
            'received_at' => Carbon::parse('2026-04-12 10:00:00'),
        ]);

        CallTraceEvent::query()->create([
            'call_session_id' => $session->id,
            'call_uuid' => $session->call_uuid,
            'node_id' => 'node-1',
            'node_type' => 'ring_group',
            'action' => 'flow.node.executing',
            'payload' => ['node_label' => 'Support queue'],
            'occurred_at' => Carbon::parse('2026-04-12 10:00:05'),
        ]);

        CallTraceEvent::query()->create([
            'call_session_id' => $session->id,
            'call_uuid' => $session->call_uuid,
            'node_id' => 'node-1',
            'node_type' => 'ring_group',
            'action' => 'flow.node.executed',
            'payload' => ['result' => 'connected'],
            'occurred_at' => Carbon::parse('2026-04-12 10:00:30'),
        ]);

        $winningAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($session)
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
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $endpoint->id,
            'push_type' => 'wake',
            'status' => 'sent',
            'sent_at' => Carbon::parse('2026-04-12 10:00:08'),
            'response_payload' => ['provider' => 'fcm'],
        ]);

        $session->forceFill([
            'variables' => [
                'winner_attempt_id' => $winningAttempt->id,
                'winner_leg_uuid' => 'leg-123',
                'winner_committed_at' => Carbon::parse('2026-04-12 10:00:25')->toIso8601String(),
            ],
        ])->save();

        $service = app(InteractionOverviewService::class);
        $overview = $service->build($tenant, $session);

        $this->assertSame('call-123', $overview['call_uuid']);
        $this->assertSame('bridged', $overview['state']);
        $this->assertSame('Bridged', $overview['summary']['status_label']);
        $this->assertSame('Answered via push to ios mobile app', $overview['summary']['outcome_label']);
        $this->assertSame(1, $overview['summary']['delivery_attempt_count']);
        $this->assertSame(1, $overview['summary']['push_notification_count']);
        $this->assertSame(1, $overview['summary']['call_event_count']);
        $this->assertSame(2, $overview['summary']['trace_event_count']);
        $this->assertSame(4, count($overview['timeline']));
        $this->assertSame('call.created', $overview['timeline'][0]['type']);
        $this->assertSame('flow.node.executing', $overview['timeline'][1]['type']);
        $this->assertSame('Call entered ring group', $overview['timeline'][1]['details']['label']);
        $this->assertSame('push.sent', $overview['timeline'][2]['type']);
        $this->assertSame('delivery.push.won', $overview['timeline'][3]['type']);
        $this->assertSame('Answered via push to ios mobile app', $overview['timeline'][3]['details']['label']);
        $this->assertSame($winningAttempt->id, $overview['winning_attempt']['attempt_id']);
        $this->assertSame('2026-04-12T10:00:25+00:00', $overview['winning_attempt']['committed_at']);
        $this->assertCount(1, $overview['delivery_attempts']);
        $this->assertIsString($overview['delivery_attempts'][0]['started_at']);
        $this->assertIsString($overview['push_notification_logs'][0]['sent_at']);
        $this->assertSame([], $overview['trace_analysis']['errors']);
        $this->assertCount(1, $overview['push_notification_logs']);
    }

    public function test_it_rejects_sessions_from_other_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenantSession = CallSession::factory()->create();

        $service = app(InteractionOverviewService::class);

        $this->expectException(NotFoundHttpException::class);

        $service->build($tenant, $otherTenantSession);
    }
}
