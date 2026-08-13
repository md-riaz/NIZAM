<?php

namespace Tests\Unit\Services;

use App\Events\CallEvent;
use App\Models\CallDeliveryAttempt;
use App\Models\CallEventLog;
use App\Models\CallSession;
use App\Models\Did;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\Call\CallWinnerService;
use App\Services\Call\TraceWriter;
use App\Services\EventProcessor;
use App\Services\Media\FreeSwitchCommandService;
use App\Services\Recording\AnsweredRecordingStarter;
use App\Services\Recording\RecordingPolicyResolver;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventProcessorEventLogTest extends TestCase
{
    use RefreshDatabase;

    private EventProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        $dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->processor = new EventProcessor($dispatcher);
    }

    private function createOrganizationWithExtension(): array
    {
        $organization = Organization::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);

        return [$organization, $extension];
    }

    /**
     * traceEvents() is a shared call-trace log: CallEventIngestionService also
     * dispatches a synchronous ProcessCallEventJob for every CHANNEL_ANSWER (see
     * CallEventProcessor::process), which appends its own unrelated
     * "call.event.processed" entry regardless of recording/winner outcome. Scope
     * to recording.* actions so recording-flow assertions aren't tripped by that
     * unrelated bookkeeping.
     */
    private function recordingTraceActions(CallSession $session): array
    {
        return $session->traceEvents()
            ->orderBy('created_at')
            ->where('action', 'like', 'recording.%')
            ->pluck('action')
            ->all();
    }

    public function test_channel_create_persists_call_event(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-create-log',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'organization_id' => $organization->id,
            'call_uuid' => 'uuid-create-log',
            'event_type' => CallEventLog::EVENT_CALL_CREATED,
        ]);
    }

    public function test_channel_answer_persists_call_event(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-answer-log',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'call_uuid' => 'uuid-answer-log',
            'event_type' => CallEventLog::EVENT_CALL_ANSWERED,
        ]);
    }

    public function test_channel_bridge_persists_call_event(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-bridge-log',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Other-Leg-Unique-ID' => 'other-leg-123',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'call_uuid' => 'uuid-bridge-log',
            'event_type' => CallEventLog::EVENT_CALL_BRIDGED,
        ]);
    }

    public function test_channel_hangup_persists_call_event(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-hangup-log',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_duration' => '60',
            'variable_billsec' => '55',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'call_uuid' => 'uuid-hangup-log',
            'event_type' => CallEventLog::EVENT_CALL_HANGUP,
        ]);
    }

    public function test_registration_persists_call_event(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::register',
            'domain' => 'test.example.com',
            'from-user' => '1001',
            'contact' => 'sip:1001@192.168.1.100:5060',
        ];

        $this->processor->process($event);

        $this->assertDatabaseHas('call_events', [
            'organization_id' => $organization->id,
            'event_type' => CallEventLog::EVENT_DEVICE_REGISTERED,
        ]);
    }

    public function test_call_event_payload_contains_data(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_CREATE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'uuid-payload-check',
            'Caller-Caller-ID-Name' => 'Jane Doe',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '2001',
            'Call-Direction' => 'outbound',
        ];

        $this->processor->process($event);

        $logged = CallEventLog::where('call_uuid', 'uuid-payload-check')->first();
        $this->assertNotNull($logged);
        $this->assertEquals('Jane Doe', $logged->payload['metadata']['caller_id_name']);
        $this->assertEquals('2001', $logged->payload['metadata']['destination_number']);
    }

    public function test_full_call_lifecycle_creates_ordered_events(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $uuid = 'uuid-lifecycle-test';

        $events = [
            ['Event-Name' => 'CHANNEL_CREATE', 'Unique-ID' => $uuid],
            ['Event-Name' => 'CHANNEL_ANSWER', 'Unique-ID' => $uuid],
            ['Event-Name' => 'CHANNEL_BRIDGE', 'Unique-ID' => $uuid, 'Other-Leg-Unique-ID' => 'leg-2'],
            ['Event-Name' => 'CHANNEL_HANGUP_COMPLETE', 'Unique-ID' => $uuid, 'Hangup-Cause' => 'NORMAL_CLEARING', 'variable_duration' => '30', 'variable_billsec' => '25'],
        ];

        foreach ($events as $event) {
            $event += [
                'variable_domain_name' => 'test.example.com',
                'Caller-Caller-ID-Name' => 'Test',
                'Caller-Caller-ID-Number' => '1001',
                'Caller-Destination-Number' => '1002',
                'Call-Direction' => 'inbound',
            ];
            $this->processor->process($event);
        }

        $loggedEvents = CallEventLog::where('call_uuid', $uuid)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $this->assertCount(4, $loggedEvents);
        $this->assertEquals(CallEventLog::EVENT_CALL_CREATED, $loggedEvents[0]->event_type);
        $this->assertEquals(CallEventLog::EVENT_CALL_ANSWERED, $loggedEvents[1]->event_type);
        $this->assertEquals(CallEventLog::EVENT_CALL_BRIDGED, $loggedEvents[2]->event_type);
        $this->assertEquals(CallEventLog::EVENT_CALL_HANGUP, $loggedEvents[3]->event_type);
    }

    public function test_channel_answer_attaches_event_to_orchestrated_session_marks_attempt_answered_and_elects_winner(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-uuid',
            'state' => 'parked',
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create();
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_INITIATED,
            'freeswitch_leg_uuid' => 'b-leg-uuid',
        ]);

        $winnerService = $this->createMock(CallWinnerService::class);
        $winnerService->expects($this->once())
            ->method('electWinner')
            ->with(
                $this->callback(fn (CallSession $candidate): bool => $candidate->is($session)),
                $this->callback(fn (CallDeliveryAttempt $candidate): bool => $candidate->is($attempt))
            )
            ->willReturn([
                'status' => 'winner_committed',
                'winner_attempt_id' => $attempt->id,
                'attempt_id' => $attempt->id,
                'call_session_id' => $session->id,
            ]);

        $processor = new EventProcessor($this->createMock(WebhookDispatcher::class), null, null, $winnerService);

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'b-leg-uuid',
            'variable_nizam_call_uuid' => 'caller-leg-uuid',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $processor->process($event);

        $attempt->refresh();
        $logged = CallEventLog::where('call_uuid', 'b-leg-uuid')->firstOrFail();

        $this->assertSame(CallDeliveryAttempt::STATUS_ANSWERED, $attempt->status);
        $this->assertNotNull($attempt->answered_at);
        $this->assertSame($session->id, $logged->call_session_id);
        $this->assertSame($attempt->id, $logged->payload['metadata']['delivery_attempt_id']);
        $this->assertSame('caller-leg-uuid', $logged->payload['metadata']['caller_leg_uuid']);
    }

    public function test_channel_answer_for_winner_starts_recording_and_persists_resolution_trace(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $organization->forceFill(['recording_policy' => 'off'])->save();
        $extension->forceFill(['recording_policy' => 'inherit'])->save();
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'recording_policy' => 'all',
        ]);
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'did_id' => $did->id,
            'call_uuid' => 'caller-leg-recording',
            'state' => 'parked',
            'variables' => [
                'recording_context' => [
                    'direction' => 'inbound',
                    'organization_id' => $organization->id,
                    'organization_policy' => 'off',
                    'did_id' => $did->id,
                    'did_policy' => 'all',
                    'owner_extension_id' => null,
                    'answered_target_type' => null,
                ],
            ],
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create();
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_RINGING,
            'freeswitch_leg_uuid' => 'winner-answer-b-leg',
        ]);

        $winnerService = $this->createMock(CallWinnerService::class);
        $winnerService->expects($this->once())
            ->method('electWinner')
            ->willReturnCallback(function (CallSession $candidateSession, CallDeliveryAttempt $candidateAttempt) use ($attempt): array {
                $candidateSession->forceFill([
                    'variables' => [
                        ...($candidateSession->variables ?? []),
                        'winner_attempt_id' => $candidateAttempt->id,
                        'winner_leg_uuid' => $candidateAttempt->freeswitch_leg_uuid,
                    ],
                ])->save();

                $candidateAttempt->forceFill([
                    'status' => CallDeliveryAttempt::STATUS_WON,
                ])->save();

                return [
                    'status' => 'winner_committed',
                    'winner_attempt_id' => $attempt->id,
                    'attempt_id' => $attempt->id,
                    'call_session_id' => $candidateSession->id,
                ];
            });

        $freeSwitch = $this->createMock(FreeSwitchCommandService::class);
        $freeSwitch->expects($this->once())
            ->method('execute')
            ->with(
                'uuid_record',
                $this->callback(function (array $arguments): bool {
                    return $arguments[0] === 'caller-leg-recording'
                        && $arguments[1] === 'start'
                        && str_ends_with($arguments[2], '/caller-leg-recording.wav');
                }),
                false
            )
            ->willReturn(['executed' => true]);

        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class),
            null,
            null,
            $winnerService,
            null,
            null,
            null,
            null,
            new RecordingPolicyResolver,
            new AnsweredRecordingStarter($freeSwitch),
            new TraceWriter,
        );

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'winner-answer-b-leg',
            'variable_nizam_call_uuid' => 'caller-leg-recording',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $processor->process($event);

        $session->refresh();
        $attempt->refresh();

        $this->assertSame(CallDeliveryAttempt::STATUS_WON, $attempt->status);
        $this->assertTrue((bool) data_get($session->variables, 'recording_started'));
        $this->assertTrue((bool) data_get($session->variables, 'recording_attempted'));
        $this->assertSame('extension', data_get($session->variables, 'recording_context.answered_target_type'));
        $this->assertSame($extension->id, data_get($session->variables, 'recording_context.owner_extension_id'));
        $this->assertSame('all', data_get($session->variables, 'recording_policy_resolution.resolved_mode'));
        $this->assertTrue((bool) data_get($session->variables, 'recording_policy_resolution.should_record'));
        $this->assertSame('did', data_get($session->variables, 'recording_policy_resolution.winning_scope'));
        $this->assertNotNull(data_get($session->variables, 'recording_path'));

        $this->assertSame([
            'recording.policy.resolved',
            'recording.start.requested',
            'recording.start.succeeded',
        ], $this->recordingTraceActions($session));
    }

    public function test_duplicate_channel_answer_for_winner_does_not_repeat_recording_flow(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $organization->forceFill(['recording_policy' => 'all'])->save();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-duplicate-answer',
            'state' => 'bridged',
            'variables' => [
                'winner_attempt_id' => 'placeholder',
                'recording_started' => true,
                'recording_attempted' => true,
                'recording_path' => storage_path('app/recordings/'.$organization->id.'/caller-leg-duplicate-answer.wav'),
                'recording_context' => [
                    'direction' => 'inbound',
                    'organization_id' => $organization->id,
                    'organization_policy' => 'all',
                    'answered_target_type' => 'extension',
                ],
                'recording_policy_resolution' => [
                    'resolved_mode' => 'all',
                    'should_record' => true,
                    'winning_scope' => 'organization',
                    'resolution_chain' => ['organization:all'],
                    'reason' => 'organization policy enables inbound recording',
                ],
            ],
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create();
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_WON,
            'freeswitch_leg_uuid' => 'duplicate-answer-b-leg',
        ]);

        $session->forceFill([
            'variables' => [
                ...($session->variables ?? []),
                'winner_attempt_id' => $attempt->id,
            ],
        ])->save();

        $freeSwitch = $this->createMock(FreeSwitchCommandService::class);
        $freeSwitch->expects($this->never())->method('execute');

        $winnerService = $this->createMock(CallWinnerService::class);
        $winnerService->expects($this->never())->method('electWinner');

        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class),
            null,
            null,
            $winnerService,
            null,
            null,
            null,
            null,
            new RecordingPolicyResolver,
            new AnsweredRecordingStarter($freeSwitch),
            new TraceWriter,
        );

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'duplicate-answer-b-leg',
            'variable_nizam_call_uuid' => 'caller-leg-duplicate-answer',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $processor->process($event);

        $session->refresh();
        $attempt->refresh();

        $this->assertSame(CallDeliveryAttempt::STATUS_WON, $attempt->status);
        $this->assertTrue((bool) data_get($session->variables, 'recording_started'));
        $this->assertSame(storage_path('app/recordings/'.$organization->id.'/caller-leg-duplicate-answer.wav'), data_get($session->variables, 'recording_path'));
        $this->assertCount(0, $this->recordingTraceActions($session));
    }

    public function test_duplicate_channel_answer_with_existing_disabled_resolution_does_not_repeat_trace_flow(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $organization->forceFill(['recording_policy' => 'off'])->save();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-duplicate-disabled-answer',
            'state' => 'bridged',
            'variables' => [
                'winner_attempt_id' => 'placeholder',
                'recording_context' => [
                    'direction' => 'inbound',
                    'organization_id' => $organization->id,
                    'organization_policy' => 'off',
                    'did_id' => null,
                    'did_policy' => null,
                    'owner_extension_id' => $extension->id,
                    'extension_policy' => $extension->recording_policy,
                    'answered_target_type' => 'extension',
                ],
                'recording_policy_resolution' => [
                    'resolved_mode' => 'off',
                    'should_record' => false,
                    'winning_scope' => 'organization',
                    'resolution_chain' => ['organization:off'],
                    'reason' => 'organization policy disables recording',
                ],
            ],
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create();
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_WON,
            'freeswitch_leg_uuid' => 'duplicate-disabled-answer-b-leg',
        ]);

        $session->forceFill([
            'variables' => [
                ...($session->variables ?? []),
                'winner_attempt_id' => $attempt->id,
            ],
        ])->save();

        $freeSwitch = $this->createMock(FreeSwitchCommandService::class);
        $freeSwitch->expects($this->never())->method('execute');

        $winnerService = $this->createMock(CallWinnerService::class);
        $winnerService->expects($this->never())->method('electWinner');

        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class),
            null,
            null,
            $winnerService,
            null,
            null,
            null,
            null,
            new RecordingPolicyResolver,
            new AnsweredRecordingStarter($freeSwitch),
            new TraceWriter,
        );

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'duplicate-disabled-answer-b-leg',
            'variable_nizam_call_uuid' => 'caller-leg-duplicate-disabled-answer',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $processor->process($event);

        $session->refresh();
        $attempt->refresh();

        $this->assertSame(CallDeliveryAttempt::STATUS_WON, $attempt->status);
        $this->assertFalse((bool) data_get($session->variables, 'recording_policy_resolution.should_record'));
        $this->assertCount(0, $this->recordingTraceActions($session));
    }

    public function test_channel_answer_for_non_winner_does_not_start_recording(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $organization->forceFill(['recording_policy' => 'all'])->save();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-non-winner-recording',
            'state' => 'parked',
            'variables' => [
                'winner_attempt_id' => 'other-attempt-id',
                'recording_context' => [
                    'direction' => 'inbound',
                    'organization_id' => $organization->id,
                    'organization_policy' => 'all',
                    'answered_target_type' => null,
                ],
            ],
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create();
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_RINGING,
            'freeswitch_leg_uuid' => 'loser-answer-b-leg',
        ]);

        $freeSwitch = $this->createMock(FreeSwitchCommandService::class);
        $freeSwitch->expects($this->never())->method('execute');

        $winnerService = $this->createMock(CallWinnerService::class);
        $winnerService->expects($this->never())->method('electWinner');

        $processor = new EventProcessor(
            $this->createMock(WebhookDispatcher::class),
            null,
            null,
            $winnerService,
            null,
            null,
            null,
            null,
            new RecordingPolicyResolver,
            new AnsweredRecordingStarter($freeSwitch),
            new TraceWriter,
        );

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_ANSWER',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'loser-answer-b-leg',
            'variable_nizam_call_uuid' => 'caller-leg-non-winner-recording',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $processor->process($event);

        $session->refresh();
        $attempt->refresh();

        $this->assertSame(CallDeliveryAttempt::STATUS_ANSWERED, $attempt->status);
        $this->assertNull(data_get($session->variables, 'recording_started'));
        $this->assertNull(data_get($session->variables, 'recording_policy_resolution'));
        $this->assertCount(0, $this->recordingTraceActions($session));
    }

    public function test_channel_bridge_persists_orchestrated_bridge_metadata(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-bridge',
            'state' => 'parked',
            'variables' => [
                'nizam_delivery_target_type' => 'extension',
                'nizam_delivery_target_id' => $extension->id,
            ],
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create();
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_ANSWERED,
            'freeswitch_leg_uuid' => 'bridge-b-leg',
        ]);

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'bridge-b-leg',
            'Other-Leg-Unique-ID' => 'caller-leg-bridge',
            'variable_nizam_call_uuid' => 'caller-leg-bridge',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        $session->refresh();
        $attempt->refresh();
        $logged = CallEventLog::where('call_uuid', 'bridge-b-leg')->firstOrFail();

        $this->assertSame($session->id, $logged->call_session_id);
        $this->assertSame('bridge-b-leg', data_get($session->variables, 'delivery_bridge_leg_uuid'));
        $this->assertSame('caller-leg-bridge', data_get($session->variables, 'delivery_bridge_other_leg_uuid'));
        $this->assertSame($attempt->id, data_get($session->variables, 'delivery_bridge_attempt_id'));
        $this->assertSame('caller-leg-bridge', data_get($attempt->metadata, 'bridge_other_leg_uuid'));
        $this->assertNull(data_get($session->variables, 'winner_bridge_leg_uuid'));
    }

    public function test_channel_bridge_finalizes_committed_winner_bridge_metadata_only_for_winner(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-winning-bridge',
            'state' => 'bridged',
            'variables' => [
                'winner_attempt_id' => 'placeholder',
            ],
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create();
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_WON,
            'freeswitch_leg_uuid' => 'winner-bridge-b-leg',
        ]);
        $session->forceFill([
            'variables' => [
                ...($session->variables ?? []),
                'winner_attempt_id' => $attempt->id,
            ],
        ])->save();

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'winner-bridge-b-leg',
            'Other-Leg-Unique-ID' => 'caller-leg-winning-bridge',
            'variable_nizam_call_uuid' => 'caller-leg-winning-bridge',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        $session->refresh();
        $attempt->refresh();

        $this->assertSame('winner-bridge-b-leg', data_get($session->variables, 'winner_bridge_leg_uuid'));
        $this->assertSame('caller-leg-winning-bridge', data_get($session->variables, 'winner_bridge_other_leg_uuid'));
        $this->assertNotNull(data_get($session->variables, 'winner_bridge_completed_at'));
        $this->assertSame('winner-bridge-b-leg', data_get($attempt->metadata, 'winner_bridge_leg_uuid'));
        $this->assertSame('caller-leg-winning-bridge', data_get($attempt->metadata, 'winner_bridge_other_leg_uuid'));
        $this->assertNotNull(data_get($attempt->metadata, 'winner_bridge_completed_at'));
    }

    public function test_channel_bridge_for_non_winning_leg_does_not_finalize_winner_bridge_metadata(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-non-winning-bridge',
            'state' => 'bridged',
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create();
        $winnerAttempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_WON,
            'freeswitch_leg_uuid' => 'winner-leg-uuid',
        ]);
        $loserAttempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_LOST,
            'freeswitch_leg_uuid' => 'loser-leg-uuid',
            'failure_reason' => 'winner_already_committed',
        ]);
        $session->forceFill([
            'variables' => [
                'winner_attempt_id' => $winnerAttempt->id,
                'winner_leg_uuid' => $winnerAttempt->freeswitch_leg_uuid,
            ],
        ])->save();

        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'loser-leg-uuid',
            'Other-Leg-Unique-ID' => 'caller-leg-non-winning-bridge',
            'variable_nizam_call_uuid' => 'caller-leg-non-winning-bridge',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
        ];

        $this->processor->process($event);

        $session->refresh();
        $winnerAttempt->refresh();
        $loserAttempt->refresh();

        $this->assertNull(data_get($session->variables, 'winner_bridge_leg_uuid'));
        $this->assertNull(data_get($winnerAttempt->metadata, 'winner_bridge_leg_uuid'));
        $this->assertNull(data_get($loserAttempt->metadata, 'winner_bridge_leg_uuid'));
        $this->assertSame(CallDeliveryAttempt::STATUS_WON, $winnerAttempt->status);
        $this->assertSame(CallDeliveryAttempt::STATUS_LOST, $loserAttempt->status);
        $this->assertSame('winner_already_committed', $loserAttempt->failure_reason);
    }

    public function test_channel_hangup_marks_awaiting_pstn_confirmation_attempt_as_confirmation_failure(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-pstn-confirmation-failure',
            'state' => 'parked',
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->pstnForward()->create([
            'forward_number' => '+15551234567',
            'forward_requires_confirm' => true,
        ]);
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_PSTN,
            'status' => CallDeliveryAttempt::STATUS_ANSWERED,
            'freeswitch_leg_uuid' => 'pstn-awaiting-confirm-leg',
            'metadata' => [
                'requires_confirmation' => true,
                'awaiting_confirmation' => true,
            ],
        ]);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'pstn-awaiting-confirm-leg',
            'variable_nizam_call_uuid' => 'caller-leg-pstn-confirmation-failure',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NO_USER_RESPONSE',
            'variable_duration' => '20',
            'variable_billsec' => '10',
        ];

        $this->processor->process($event);

        $attempt->refresh();

        $this->assertSame(CallDeliveryAttempt::STATUS_FAILED, $attempt->status);
        $this->assertSame('confirmation_not_received', $attempt->failure_reason);
        $this->assertFalse((bool) data_get($attempt->metadata, 'awaiting_confirmation'));
        $this->assertSame('NO_USER_RESPONSE', data_get($attempt->metadata, 'confirmation_failure_cause'));
        $this->assertNotNull(data_get($attempt->metadata, 'confirmation_failed_at'));
    }

    public function test_channel_hangup_for_winner_cleans_up_other_attempts_and_ends_session_when_no_active_attempts_remain(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-winning-hangup',
            'state' => 'bridged',
            'variables' => [
                'winner_attempt_id' => 'placeholder',
            ],
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create();
        $winnerAttempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_WON,
            'freeswitch_leg_uuid' => 'winner-hangup-leg',
            'answered_at' => now(),
        ]);
        $lateAttempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_LATE_SIP,
            'status' => CallDeliveryAttempt::STATUS_RINGING,
            'freeswitch_leg_uuid' => 'late-ringing-leg',
        ]);
        $session->forceFill([
            'variables' => [
                ...($session->variables ?? []),
                'winner_attempt_id' => $winnerAttempt->id,
                'winner_leg_uuid' => $winnerAttempt->freeswitch_leg_uuid,
            ],
        ])->save();

        $winnerService = $this->createMock(CallWinnerService::class);
        $winnerService->expects($this->once())
            ->method('cleanupAfterWinnerHangup')
            ->with(
                $this->callback(fn (CallSession $candidate): bool => $candidate->id === $session->id),
                $this->callback(fn (CallDeliveryAttempt $candidate): bool => $candidate->id === $winnerAttempt->id)
            )
            ->willReturnCallback(function () use ($lateAttempt): void {
                $lateAttempt->forceFill([
                    'status' => CallDeliveryAttempt::STATUS_LOST,
                    'ended_at' => now(),
                    'failure_reason' => 'winner_hangup_cleanup',
                ])->save();
            });

        $processor = new EventProcessor($this->createMock(WebhookDispatcher::class), null, null, $winnerService);

        $event = [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'winner-hangup-leg',
            'variable_nizam_call_uuid' => 'caller-leg-winning-hangup',
            'variable_sip_h_X-Nizam-Call-Session-Id' => $session->id,
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_duration' => '42',
            'variable_billsec' => '40',
        ];

        $processor->process($event);

        $session->refresh();
        $winnerAttempt->refresh();
        $lateAttempt->refresh();

        $this->assertNotNull($winnerAttempt->ended_at);
        $this->assertSame(CallDeliveryAttempt::STATUS_WON, $winnerAttempt->status);
        $this->assertSame(CallDeliveryAttempt::STATUS_LOST, $lateAttempt->status);
        $this->assertSame('ended', $session->state);
        $this->assertNotNull($session->ended_at);
        $this->assertNotNull(data_get($session->variables, 'winner_hangup_completed_at'));
    }
}
