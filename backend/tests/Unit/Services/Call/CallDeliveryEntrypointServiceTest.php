<?php

namespace Tests\Unit\Services\Call;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\Tenant;
use App\Services\Call\CallDeliveryEntrypointService;
use App\Services\Call\DeliveryPlanItem;
use App\Services\Call\LiveRegistrationVisibility;
use App\Services\Call\OfferCommandDispatcher;
use App\Services\Call\OfferCommandResult;
use App\Services\Call\ReachabilityCache;
use App\Services\Call\ReachabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallDeliveryEntrypointServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'call_delivery.reachability.cache_store' => 'array',
            'call_delivery.reachability.cache_ttl_seconds' => 30,
            'call_delivery.reachability.wake_window_seconds' => 45,
        ]);
    }

    public function test_entrypoint_creates_or_loads_session_parks_caller_and_invokes_orchestration_once(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'acme.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret',
            'directory_first_name' => 'Desk',
            'directory_last_name' => 'Phone',
            'voicemail_enabled' => true,
            'is_active' => true,
        ]);

        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);

        $service = $this->makeServiceWithLiveRegistrations($tenant, [
            '1001' => [
                'registered' => true,
                'registration_user' => '1001',
                'source' => 'esl_live',
            ],
        ]);

        $session = $service->enter($tenant, 'call-uuid-1', [
            'target_type' => 'extension',
            'target_id' => $extension->id,
            'caller_leg_uuid' => 'call-uuid-1',
            'caller_id_name' => 'Alice',
            'caller_id_number' => '+15550001111',
            'destination_number' => 'call_delivery_entrypoint',
            'domain' => $tenant->domain,
            'auto_answer_enabled' => true,
            'auto_answer_call_info' => 'answer-after=0',
            'auto_answer_alert_info' => 'intercom',
        ]);

        $this->assertTrue((bool) data_get($session->variables, 'nizam_auto_answer_enabled'));
        $this->assertSame('answer-after=0', data_get($session->variables, 'nizam_auto_answer_call_info'));
        $this->assertSame('intercom', data_get($session->variables, 'nizam_auto_answer_alert_info'));

        $session->refresh();

        $this->assertSame('parked', $session->state);
        $this->assertSame('extension', data_get($session->variables, 'nizam_delivery_target_type'));
        $this->assertSame($extension->id, data_get($session->variables, 'nizam_delivery_target_id'));
        $this->assertSame(1, data_get($session->variables, 'delivery_entrypoint_invocations'));
        $this->assertSame(1, CallSession::query()->where('call_uuid', 'call-uuid-1')->count());
        $this->assertSame(1, $session->deliveryAttempts()->count());

        $attempt = $session->deliveryAttempts()->firstOrFail();
        $this->assertSame($binding->id, $attempt->endpoint_binding_id);
        $this->assertSame(CallDeliveryAttempt::TYPE_SIP, $attempt->attempt_type);
        $this->assertSame(CallDeliveryAttempt::STATUS_INITIATED, $attempt->status);
    }

    public function test_entrypoint_is_idempotent_when_active_attempts_already_exist(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'acme.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '1002',
            'password' => 'secret',
            'directory_first_name' => 'Repeat',
            'directory_last_name' => 'Target',
            'voicemail_enabled' => true,
            'is_active' => true,
        ]);

        EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);

        $service = $this->makeServiceWithLiveRegistrations($tenant, [
            '1002' => [
                'registered' => true,
                'registration_user' => '1002',
                'source' => 'esl_live',
            ],
        ]);

        $service->enter($tenant, 'call-uuid-2', [
            'target_type' => 'extension',
            'target_id' => $extension->id,
            'caller_leg_uuid' => 'call-uuid-2',
            'caller_id_number' => '+15550002222',
            'domain' => $tenant->domain,
        ]);

        $service->enter($tenant, 'call-uuid-2', [
            'target_type' => 'extension',
            'target_id' => $extension->id,
            'caller_leg_uuid' => 'call-uuid-2',
            'caller_id_number' => '+15550002222',
            'domain' => $tenant->domain,
        ]);

        $session = CallSession::query()->where('call_uuid', 'call-uuid-2')->firstOrFail();

        $this->assertSame(1, CallSession::query()->where('call_uuid', 'call-uuid-2')->count());
        $this->assertSame(1, $session->deliveryAttempts()->count());
        $this->assertSame(2, data_get($session->variables, 'delivery_entrypoint_invocations'));
    }

    public function test_entrypoint_does_not_restart_after_winner_commit(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'acme.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '1003',
            'password' => 'secret',
            'directory_first_name' => 'Winner',
            'directory_last_name' => 'Committed',
            'voicemail_enabled' => true,
            'is_active' => true,
        ]);

        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);

        $session = CallSession::factory()->for($tenant)->create([
            'call_uuid' => 'call-uuid-3',
            'state' => 'bridged',
            'variables' => [
                'nizam_delivery_target_type' => 'extension',
                'nizam_delivery_target_id' => $extension->id,
                'winner_attempt_id' => 'winner-attempt-id',
            ],
        ]);

        $attempt = $session->deliveryAttempts()->create([
            'endpoint_binding_id' => $binding->id,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'status' => CallDeliveryAttempt::STATUS_WON,
            'freeswitch_leg_uuid' => 'winner-leg',
            'started_at' => now(),
        ]);

        $session->forceFill([
            'variables' => [
                ...($session->variables ?? []),
                'winner_attempt_id' => $attempt->id,
            ],
        ])->save();

        $service = $this->makeServiceWithLiveRegistrations($tenant, [
            '1003' => [
                'registered' => true,
                'registration_user' => '1003',
                'source' => 'esl_live',
            ],
        ]);

        $service->enter($tenant, 'call-uuid-3', [
            'target_type' => 'extension',
            'target_id' => $extension->id,
            'caller_leg_uuid' => 'call-uuid-3',
            'caller_id_number' => '+15550003333',
            'domain' => $tenant->domain,
        ]);

        $session->refresh();

        $this->assertSame('bridged', $session->state);
        $this->assertSame(1, $session->deliveryAttempts()->count());
        $this->assertSame($attempt->id, data_get($session->variables, 'winner_attempt_id'));
        $this->assertSame(1, data_get($session->variables, 'delivery_entrypoint_invocations'));
    }

    protected function makeServiceWithLiveRegistrations(Tenant $tenant, array $registrations): CallDeliveryEntrypointService
    {
        $visibility = $this->createMock(LiveRegistrationVisibility::class);
        $visibility->method('forTenant')
            ->willReturnCallback(fn (Tenant $resolvedTenant): array => $resolvedTenant->id === $tenant->id ? $registrations : []);

        $this->app->instance(ReachabilityResolver::class, new ReachabilityResolver(new ReachabilityCache, $visibility));
        $this->app->instance(OfferCommandDispatcher::class, new FakeEntrypointOfferCommandDispatcher);

        return app(CallDeliveryEntrypointService::class);
    }
}

class FakeEntrypointOfferCommandDispatcher implements OfferCommandDispatcher
{
    public function originateSip(DeliveryPlanItem $item, array $context = []): OfferCommandResult
    {
        return OfferCommandResult::success('sip-ok', 'sip-'.$item->candidate->endpointBindingId);
    }

    public function originatePstn(DeliveryPlanItem $item, array $context = []): OfferCommandResult
    {
        return OfferCommandResult::success('pstn-ok', 'pstn-'.$item->candidate->endpointBindingId);
    }
}
