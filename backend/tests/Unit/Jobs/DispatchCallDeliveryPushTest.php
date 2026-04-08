<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DispatchCallDeliveryPush;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DispatchCallDeliveryPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'telephony.push_driver' => 'log',
        ]);
    }

    public function test_marks_ios_push_as_sent_via_apns_on_log_driver(): void
    {
        Log::spy();

        $tenant = Tenant::factory()->create(['domain' => 'ios.push.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '3001',
            'password' => 'secret',
            'directory_first_name' => 'iOS',
            'directory_last_name' => 'Device',
            'voicemail_enabled' => false,
            'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => EndpointBinding::PLATFORM_IOS,
            'is_push_capable' => true,
            'voip_push_token' => 'test-voip-token-ios',
            'push_token' => null,
        ]);

        $session = CallSession::factory()->for($tenant)->create([
            'call_uuid' => 'ios-push-uuid-1',
            'state' => 'parked',
        ]);

        $log = $session->pushNotificationLogs()->create([
            'endpoint_binding_id' => $binding->id,
            'push_type' => 'wake',
            'provider_message_id' => 'msg-ios-1',
            'status' => 'queued',
            'sent_at' => now(),
            'response_payload' => ['call_session_id' => $session->id],
        ]);

        $job = new DispatchCallDeliveryPush(
            $log->id,
            $session->id,
            $binding->id,
            ['call_session_id' => $session->id, 'call_uuid' => 'ios-push-uuid-1'],
        );

        $job->handle();

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame('apns_voip', data_get($log->response_payload, 'sent_via'));
        $this->assertSame('log', data_get($log->response_payload, 'driver'));
    }

    public function test_marks_android_push_as_sent_via_fcm_on_log_driver(): void
    {
        Log::spy();

        $tenant = Tenant::factory()->create(['domain' => 'android.push.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '3002',
            'password' => 'secret',
            'directory_first_name' => 'Android',
            'directory_last_name' => 'Device',
            'voicemail_enabled' => false,
            'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => EndpointBinding::PLATFORM_ANDROID,
            'is_push_capable' => true,
            'push_token' => 'test-fcm-token-android',
            'voip_push_token' => null,
        ]);

        $session = CallSession::factory()->for($tenant)->create([
            'call_uuid' => 'android-push-uuid-1',
            'state' => 'parked',
        ]);

        $log = $session->pushNotificationLogs()->create([
            'endpoint_binding_id' => $binding->id,
            'push_type' => 'wake',
            'provider_message_id' => 'msg-android-1',
            'status' => 'queued',
            'sent_at' => now(),
            'response_payload' => [],
        ]);

        $job = new DispatchCallDeliveryPush(
            $log->id,
            $session->id,
            $binding->id,
            ['call_session_id' => $session->id],
        );

        $job->handle();

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame('fcm', data_get($log->response_payload, 'sent_via'));
    }

    public function test_marks_push_as_failed_when_binding_not_found(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'notfound.push.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '3003',
            'password' => 'secret',
            'directory_first_name' => 'Ghost',
            'directory_last_name' => 'Binding',
            'voicemail_enabled' => false,
            'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => EndpointBinding::PLATFORM_IOS,
            'is_push_capable' => true,
        ]);

        $session = CallSession::factory()->for($tenant)->create([
            'call_uuid' => 'notfound-push-uuid',
            'state' => 'parked',
        ]);

        $log = $session->pushNotificationLogs()->create([
            'endpoint_binding_id' => $binding->id,
            'push_type' => 'wake',
            'provider_message_id' => 'msg-notfound',
            'status' => 'queued',
            'sent_at' => now(),
            'response_payload' => [],
        ]);

        $missingBindingId = 'nonexistent-binding-id-00000000-0000-0000-0000-000000000000';

        $job = new DispatchCallDeliveryPush(
            $log->id,
            $session->id,
            $missingBindingId,
            [],
        );

        $job->handle();

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('endpoint_binding_not_found', (string) data_get($log->response_payload, 'failure_reason'));
    }

    public function test_noop_when_log_not_found(): void
    {
        $job = new DispatchCallDeliveryPush(
            'nonexistent-log-id',
            'nonexistent-session-id',
            'nonexistent-binding-id',
            [],
        );

        // Should not throw - silently exits
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_noop_when_log_already_sent(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'already.sent.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '3004',
            'password' => 'secret',
            'directory_first_name' => 'Already',
            'directory_last_name' => 'Sent',
            'voicemail_enabled' => false,
            'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => EndpointBinding::PLATFORM_IOS,
            'is_push_capable' => true,
            'voip_push_token' => 'already-sent-token',
        ]);

        $session = CallSession::factory()->for($tenant)->create([
            'call_uuid' => 'already-sent-uuid',
            'state' => 'parked',
        ]);

        $log = $session->pushNotificationLogs()->create([
            'endpoint_binding_id' => $binding->id,
            'push_type' => 'wake',
            'provider_message_id' => 'msg-already-sent',
            'status' => 'sent',
            'sent_at' => now(),
            'response_payload' => ['sent_via' => 'apns_voip'],
        ]);

        $job = new DispatchCallDeliveryPush(
            $log->id,
            $session->id,
            $binding->id,
            [],
        );

        $job->handle();

        $log->refresh();
        // Status should remain 'sent' and not be modified
        $this->assertSame('sent', $log->status);
        $this->assertSame('apns_voip', data_get($log->response_payload, 'sent_via'));
    }

    public function test_answered_elsewhere_push_is_processed(): void
    {
        Log::spy();

        $tenant = Tenant::factory()->create(['domain' => 'elsewhere.push.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '3005',
            'password' => 'secret',
            'directory_first_name' => 'Elsewhere',
            'directory_last_name' => 'Test',
            'voicemail_enabled' => false,
            'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => EndpointBinding::PLATFORM_IOS,
            'is_push_capable' => true,
            'voip_push_token' => 'elsewhere-voip-token',
        ]);

        $session = CallSession::factory()->for($tenant)->create([
            'call_uuid' => 'elsewhere-uuid-1',
            'state' => 'bridged',
        ]);

        $log = $session->pushNotificationLogs()->create([
            'endpoint_binding_id' => $binding->id,
            'push_type' => 'answered_elsewhere',
            'provider_message_id' => 'msg-elsewhere-1',
            'status' => 'queued',
            'sent_at' => now(),
            'response_payload' => [
                'notification_type' => 'answered_elsewhere',
                'call_session_id' => $session->id,
            ],
        ]);

        $job = new DispatchCallDeliveryPush(
            $log->id,
            $session->id,
            $binding->id,
            ['notification_type' => 'answered_elsewhere', 'call_session_id' => $session->id],
        );

        $job->handle();

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame('apns_voip', data_get($log->response_payload, 'sent_via'));
    }
}
