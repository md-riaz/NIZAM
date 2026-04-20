<?php

namespace Tests\Unit\Services;

use App\Events\CallEvent;
use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\Call\CallOfferExecutor;
use App\Services\Call\ReachabilityCache;
use App\Services\EventProcessor;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventProcessorBridgeTest extends TestCase
{
    use RefreshDatabase;

    private EventProcessor $processor;

    private WebhookDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        $this->dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->processor = new EventProcessor($this->dispatcher);
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

    public function test_dispatches_bridge_event_on_channel_bridge(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-bridge',
            'Caller-Caller-ID-Name' => 'John Doe',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Other-Leg-Unique-ID' => 'test-uuid-other-leg',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($organization) {
            return $e->organizationId === $organization->id && $e->eventType === 'call.bridged';
        });
    }

    public function test_bridge_event_includes_other_leg_uuid(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-bridge-2',
            'Caller-Caller-ID-Name' => 'John Doe',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Other-Leg-Unique-ID' => 'other-leg-uuid-123',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) {
            return $e->eventType === 'call.bridged'
                && isset($e->data['metadata']['other_leg_uuid'])
                && $e->data['metadata']['other_leg_uuid'] === 'other-leg-uuid-123';
        });
    }

    public function test_bridge_event_dispatches_webhook(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        Event::fake([CallEvent::class]);

        $this->dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($organization->id, 'call.bridged', $this->anything());

        $processor = new EventProcessor($this->dispatcher);

        $event = [
            'Event-Name' => 'CHANNEL_BRIDGE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => 'test-uuid-bridge-3',
            'Caller-Caller-ID-Name' => 'John',
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Other-Leg-Unique-ID' => 'other-leg-uuid',
        ];

        $processor->process($event);
    }

    public function test_handles_registration_event(): void
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
            'user-agent' => 'Yealink SIP-T54W',
            'network-ip' => '192.168.1.100',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($organization) {
            return $e->organizationId === $organization->id && $e->eventType === 'device.registered';
        });
    }

    public function test_handles_unregistration_event(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::unregister',
            'domain' => 'test.example.com',
            'from-user' => '1001',
        ];

        $this->processor->process($event);

        Event::assertDispatched(CallEvent::class, function (CallEvent $e) use ($organization) {
            return $e->organizationId === $organization->id && $e->eventType === 'device.unregistered';
        });
    }

    public function test_registration_dispatches_webhook(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);
        Event::fake([CallEvent::class]);

        $this->dispatcher = $this->createMock(WebhookDispatcher::class);
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($organization->id, 'device.registered', $this->anything());

        $processor = new EventProcessor($this->dispatcher);

        $event = [
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::register',
            'domain' => 'test.example.com',
            'from-user' => '1001',
        ];

        $processor->process($event);
    }

    public function test_ignores_registration_for_unknown_domain(): void
    {
        Event::fake([CallEvent::class]);

        $event = [
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::register',
            'domain' => 'unknown.example.com',
            'from-user' => '1001',
        ];

        $this->processor->process($event);

        Event::assertNotDispatched(CallEvent::class);
    }

    public function test_registration_updates_reachability_and_originates_single_late_join_attempt_when_session_is_eligible(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'allow_late_join_after_push' => true,
            'is_push_capable' => true,
            'push_token' => 'push-token',
        ]);
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-register-late-join',
            'state' => 'parked',
            'variables' => [
                'delivery_wake_window_until' => now()->addSeconds(30)->toIso8601String(),
                'delivery_late_join_bindings' => [
                    $binding->id => [
                        'late_join_window_until' => now()->addSeconds(30)->toIso8601String(),
                    ],
                ],
                'caller_id_name' => 'Caller',
                'caller_id_number' => '1000',
            ],
        ]);
        CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_PUSH,
            'status' => CallDeliveryAttempt::STATUS_INITIATED,
        ]);

        $cache = $this->createMock(ReachabilityCache::class);
        $cache->expects($this->once())
            ->method('markRegistered')
            ->with(
                $organization->id,
                $this->callback(fn ($candidate): bool => $candidate->endpointBindingId === $binding->id),
                $this->callback(fn (array $attributes): bool => ($attributes['contact'] ?? null) === 'sip:1001@192.168.1.100:5060')
            );

        $offerExecutor = $this->createMock(CallOfferExecutor::class);
        $offerExecutor->expects($this->once())
            ->method('executePlan')
            ->with(
                $this->callback(function ($plan) use ($session, $binding): bool {
                    return $plan->callSessionId === $session->id
                        && count($plan->immediateSipWave) === 1
                        && $plan->immediateSipWave[0]->attemptType === CallDeliveryAttempt::TYPE_LATE_SIP
                        && $plan->immediateSipWave[0]->candidate->endpointBindingId === $binding->id;
                }),
                $this->callback(fn (array $context): bool => ($context['caller_leg_uuid'] ?? null) === $session->call_uuid)
            )
            ->willReturn(['sip_attempt_ids' => ['late-attempt-id'], 'push_attempt_ids' => [], 'pstn_attempt_ids' => []]);

        $processor = new EventProcessor($this->dispatcher, null, null, null, $cache, $offerExecutor);

        $processor->process([
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::register',
            'domain' => 'test.example.com',
            'from-user' => '1001',
            'contact' => 'sip:1001@192.168.1.100:5060',
            'user-agent' => 'Yealink SIP-T54W',
            'network-ip' => '192.168.1.100',
        ]);
    }

    public function test_registration_skips_duplicate_late_join_when_active_sip_attempt_already_exists(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'allow_late_join_after_push' => true,
        ]);
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-register-duplicate',
            'state' => 'parked',
            'variables' => [
                'delivery_wake_window_until' => now()->addSeconds(30)->toIso8601String(),
                'delivery_late_join_bindings' => [
                    $binding->id => [
                        'late_join_window_until' => now()->addSeconds(30)->toIso8601String(),
                    ],
                ],
            ],
        ]);
        CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_RINGING,
        ]);

        $cache = $this->createMock(ReachabilityCache::class);
        $cache->expects($this->once())->method('markRegistered');

        $offerExecutor = $this->createMock(CallOfferExecutor::class);
        $offerExecutor->expects($this->never())->method('executePlan');

        $processor = new EventProcessor($this->dispatcher, null, null, null, $cache, $offerExecutor);

        $processor->process([
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::register',
            'domain' => 'test.example.com',
            'from-user' => '1001',
            'contact' => 'sip:1001@192.168.1.100:5060',
        ]);
    }

    public function test_registration_skips_late_join_when_winner_is_already_committed(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'allow_late_join_after_push' => true,
        ]);
        $session = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'caller-leg-register-winner-exists',
            'state' => 'bridged',
            'variables' => [
                'winner_attempt_id' => 'winner-attempt-id',
                'delivery_wake_window_until' => now()->addSeconds(30)->toIso8601String(),
                'delivery_late_join_bindings' => [
                    $binding->id => [
                        'late_join_window_until' => now()->addSeconds(30)->toIso8601String(),
                    ],
                ],
            ],
        ]);
        CallDeliveryAttempt::factory()->create([
            'call_session_id' => $session->id,
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_PUSH,
            'status' => CallDeliveryAttempt::STATUS_INITIATED,
        ]);

        $cache = $this->createMock(ReachabilityCache::class);
        $cache->expects($this->once())->method('markRegistered');

        $offerExecutor = $this->createMock(CallOfferExecutor::class);
        $offerExecutor->expects($this->never())->method('executePlan');

        $processor = new EventProcessor($this->dispatcher, null, null, null, $cache, $offerExecutor);

        $processor->process([
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::register',
            'domain' => 'test.example.com',
            'from-user' => '1001',
            'contact' => 'sip:1001@192.168.1.100:5060',
        ]);
    }

    public function test_unregistration_updates_reachability_without_originating_late_join(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();
        $binding = EndpointBinding::factory()->forExtension($extension)->create();

        $cache = $this->createMock(ReachabilityCache::class);
        $cache->expects($this->once())
            ->method('markUnregistered')
            ->with(
                $organization->id,
                $this->callback(fn ($candidate): bool => $candidate->endpointBindingId === $binding->id),
                $this->anything()
            );

        $offerExecutor = $this->createMock(CallOfferExecutor::class);
        $offerExecutor->expects($this->never())->method('executePlan');

        $processor = new EventProcessor($this->dispatcher, null, null, null, $cache, $offerExecutor);

        $processor->process([
            'Event-Name' => 'CUSTOM',
            'Event-Subclass' => 'sofia::unregister',
            'domain' => 'test.example.com',
            'from-user' => '1001',
        ]);
    }
}
