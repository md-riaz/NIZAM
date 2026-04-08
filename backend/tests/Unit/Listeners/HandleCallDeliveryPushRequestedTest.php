<?php

namespace Tests\Unit\Listeners;

use App\Events\CallDeliveryPushRequested;
use App\Jobs\DispatchCallDeliveryPush;
use App\Listeners\HandleCallDeliveryPushRequested;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HandleCallDeliveryPushRequestedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_dispatches_push_job_when_queued_log_exists(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create(['domain' => 'push.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '2001',
            'password' => 'secret',
            'directory_first_name' => 'Push',
            'directory_last_name' => 'Test',
            'voicemail_enabled' => false,
            'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => EndpointBinding::PLATFORM_IOS,
            'is_push_capable' => true,
            'voip_push_token' => 'test-voip-token',
        ]);

        $session = CallSession::factory()->for($tenant)->create([
            'call_uuid' => 'push-test-uuid-1',
            'state' => 'parked',
        ]);

        $log = $session->pushNotificationLogs()->create([
            'endpoint_binding_id' => $binding->id,
            'push_type' => 'wake',
            'provider_message_id' => 'test-msg-id-1',
            'status' => 'queued',
            'sent_at' => now(),
            'response_payload' => ['call_session_id' => $session->id],
        ]);

        $event = new CallDeliveryPushRequested($session->id, $binding->id, [
            'call_session_id' => $session->id,
            'call_uuid' => 'push-test-uuid-1',
        ]);

        $listener = new HandleCallDeliveryPushRequested;
        $listener->handle($event);

        Queue::assertPushed(DispatchCallDeliveryPush::class, function (DispatchCallDeliveryPush $job) use ($log, $session, $binding) {
            return $job->pushNotificationLogId === $log->id
                && $job->callSessionId === $session->id
                && $job->endpointBindingId === $binding->id;
        });
    }

    public function test_does_not_dispatch_when_no_queued_log_exists(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create(['domain' => 'push2.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '2002',
            'password' => 'secret',
            'directory_first_name' => 'Push',
            'directory_last_name' => 'NoLog',
            'voicemail_enabled' => false,
            'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => EndpointBinding::PLATFORM_IOS,
            'is_push_capable' => true,
        ]);

        $session = CallSession::factory()->for($tenant)->create([
            'call_uuid' => 'push-test-uuid-2',
            'state' => 'parked',
        ]);

        // No push notification log exists for this session/binding combination.
        $event = new CallDeliveryPushRequested($session->id, $binding->id, []);

        $listener = new HandleCallDeliveryPushRequested;
        $listener->handle($event);

        Queue::assertNothingPushed();
    }

    public function test_does_not_dispatch_when_log_already_sent(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create(['domain' => 'push3.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '2003',
            'password' => 'secret',
            'directory_first_name' => 'Push',
            'directory_last_name' => 'Sent',
            'voicemail_enabled' => false,
            'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => EndpointBinding::PLATFORM_ANDROID,
            'is_push_capable' => true,
            'push_token' => 'test-fcm-token',
        ]);

        $session = CallSession::factory()->for($tenant)->create([
            'call_uuid' => 'push-test-uuid-3',
            'state' => 'parked',
        ]);

        $session->pushNotificationLogs()->create([
            'endpoint_binding_id' => $binding->id,
            'push_type' => 'wake',
            'provider_message_id' => 'test-msg-id-3',
            'status' => 'sent',
            'sent_at' => now(),
            'response_payload' => [],
        ]);

        $event = new CallDeliveryPushRequested($session->id, $binding->id, []);

        $listener = new HandleCallDeliveryPushRequested;
        $listener->handle($event);

        Queue::assertNothingPushed();
    }
}
