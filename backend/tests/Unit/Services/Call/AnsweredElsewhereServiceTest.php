<?php

namespace Tests\Unit\Services\Call;

use App\Events\CallDeliveryPushRequested;
use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\Organization;
use App\Services\Call\AnsweredElsewhereService;
use App\Services\Call\TraceWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AnsweredElsewhereServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_answered_elsewhere_notifications_are_not_duplicated_for_same_binding(): void
    {
        Event::fake([CallDeliveryPushRequested::class]);

        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret',
            'directory_first_name' => 'Mobile',
            'directory_last_name' => 'User',
            'voicemail_enabled' => true,
            'is_active' => true,
        ]);

        $callSession = CallSession::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => 'answered-elsewhere-call',
            'variables' => [
                'winner_leg_uuid' => 'winner-leg',
            ],
        ]);

        $winnerBinding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_DESK_PHONE,
            'is_push_capable' => false,
            'push_token' => null,
            'voip_push_token' => null,
        ]);
        $mobileBinding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'is_push_capable' => true,
            'push_token' => 'push-token',
            'voip_push_token' => 'voip-token',
        ]);

        $winnerAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($winnerBinding)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
                'status' => CallDeliveryAttempt::STATUS_WON,
                'freeswitch_leg_uuid' => 'winner-leg',
            ]);

        $losingAttempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($mobileBinding)
            ->create([
                'attempt_type' => CallDeliveryAttempt::TYPE_PUSH,
                'status' => CallDeliveryAttempt::STATUS_CANCELLED,
                'failure_reason' => 'answered_elsewhere',
            ]);

        $service = new AnsweredElsewhereService(app(TraceWriter::class));

        $service->notifyAnsweredElsewhere($callSession, $winnerAttempt, [$losingAttempt]);
        $service->notifyAnsweredElsewhere($callSession->fresh(), $winnerAttempt->fresh(), [$losingAttempt->fresh()]);

        $this->assertDatabaseCount('push_notification_logs', 1);
        $this->assertDatabaseHas('push_notification_logs', [
            'call_session_id' => $callSession->id,
            'endpoint_binding_id' => $mobileBinding->id,
            'push_type' => 'answered_elsewhere',
            'status' => 'queued',
        ]);

        Event::assertDispatchedTimes(CallDeliveryPushRequested::class, 1);
    }
}
