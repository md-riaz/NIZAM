<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DispatchCallDeliveryPush;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use App\Models\PushNotificationLog;
use App\Models\Tenant;
use App\Services\Push\ApnsPushDriver;
use App\Services\Push\FcmPushDriver;
use App\Services\Push\NullPushDriver;
use App\Services\Push\PushDeliveryResult;
use App\Services\Push\PushDriverManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchCallDeliveryPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    private function makeTenantAndBinding(string $domain, string $ext, string $platform, array $tokens = []): array
    {
        $tenant = Tenant::factory()->create(['domain' => $domain]);
        $extension = $tenant->extensions()->create([
            'extension' => $ext, 'password' => 'secret',
            'directory_first_name' => 'Test', 'directory_last_name' => 'User',
            'voicemail_enabled' => false, 'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create(array_merge([
            'type' => EndpointBinding::TYPE_MOBILE_APP,
            'platform' => $platform,
            'is_push_capable' => true,
        ], $tokens));
        $session = CallSession::factory()->for($tenant)->create(['call_uuid' => 'test-uuid', 'state' => 'parked']);

        return [$binding, $session];
    }

    private function makeLog(CallSession $session, EndpointBinding $binding, string $status = 'queued'): PushNotificationLog
    {
        return $session->pushNotificationLogs()->create([
            'endpoint_binding_id' => $binding->id,
            'push_type' => 'wake',
            'provider_message_id' => 'msg-'.uniqid(),
            'status' => $status,
            'sent_at' => now(),
            'response_payload' => [],
        ]);
    }

    public function test_ios_binding_uses_apns_driver(): void
    {
        [$binding, $session] = $this->makeTenantAndBinding('ios.test', '4001', EndpointBinding::PLATFORM_IOS, [
            'voip_push_token' => 'voip-tok', 'push_token' => null,
        ]);
        $log = $this->makeLog($session, $binding);

        $apns = $this->createMock(ApnsPushDriver::class);
        $apns->expects($this->once())->method('send')
            ->willReturn(PushDeliveryResult::sent('apns_voip', 'msg-id-1'));
        app()->instance(ApnsPushDriver::class, $apns);

        (new DispatchCallDeliveryPush($log->id, $session->id, $binding->id, []))->handle(app(PushDriverManager::class));

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame('apns_voip', data_get($log->response_payload, 'channel'));
    }

    public function test_android_binding_uses_fcm_driver(): void
    {
        [$binding, $session] = $this->makeTenantAndBinding('android.test', '4002', EndpointBinding::PLATFORM_ANDROID, [
            'push_token' => 'fcm-tok', 'voip_push_token' => null,
        ]);
        $log = $this->makeLog($session, $binding);

        $fcm = $this->createMock(FcmPushDriver::class);
        $fcm->expects($this->once())->method('send')
            ->willReturn(PushDeliveryResult::sent('fcm', 'projects/p/messages/x'));
        app()->instance(FcmPushDriver::class, $fcm);

        (new DispatchCallDeliveryPush($log->id, $session->id, $binding->id, []))->handle(app(PushDriverManager::class));

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame('fcm', data_get($log->response_payload, 'channel'));
    }

    public function test_driver_failure_marks_log_failed(): void
    {
        [$binding, $session] = $this->makeTenantAndBinding('fail.test', '4003', EndpointBinding::PLATFORM_IOS, [
            'voip_push_token' => 'voip-tok2',
        ]);
        $log = $this->makeLog($session, $binding);

        $apns = $this->createMock(ApnsPushDriver::class);
        $apns->method('send')->willReturn(PushDeliveryResult::failed('apns_rejected:BadDeviceToken'));
        app()->instance(ApnsPushDriver::class, $apns);

        (new DispatchCallDeliveryPush($log->id, $session->id, $binding->id, []))->handle(app(PushDriverManager::class));

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('apns_rejected', (string) data_get($log->response_payload, 'error'));
    }

    public function test_noop_when_log_already_sent(): void
    {
        [$binding, $session] = $this->makeTenantAndBinding('sent.test', '4004', EndpointBinding::PLATFORM_IOS, [
            'voip_push_token' => 'voip-tok3',
        ]);
        $log = $this->makeLog($session, $binding, 'sent');

        $manager = $this->createMock(PushDriverManager::class);
        $manager->expects($this->never())->method('deliver');

        (new DispatchCallDeliveryPush($log->id, $session->id, $binding->id, []))->handle($manager);

        $log->refresh();
        $this->assertSame('sent', $log->status);
    }

    public function test_noop_when_log_not_found(): void
    {
        $manager = $this->createMock(PushDriverManager::class);
        $manager->expects($this->never())->method('deliver');

        (new DispatchCallDeliveryPush('nonexistent', 'sess', 'bind', []))->handle($manager);
        $this->assertTrue(true);
    }

    public function test_marks_failed_when_binding_not_found(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'nob.test']);
        $extension = $tenant->extensions()->create([
            'extension' => '4005', 'password' => 'x',
            'directory_first_name' => 'A', 'directory_last_name' => 'B',
            'voicemail_enabled' => false, 'is_active' => true,
        ]);
        $binding = EndpointBinding::factory()->forExtension($extension)->create([
            'type' => EndpointBinding::TYPE_MOBILE_APP, 'is_push_capable' => true,
        ]);
        $session = CallSession::factory()->for($tenant)->create(['call_uuid' => 'nob-uuid', 'state' => 'parked']);
        $log = $this->makeLog($session, $binding);

        $manager = $this->createMock(PushDriverManager::class);
        $manager->expects($this->never())->method('deliver');

        (new DispatchCallDeliveryPush($log->id, $session->id, 'nonexistent-binding', []))->handle($manager);

        $log->refresh();
        $this->assertSame('failed', $log->status);
    }
}
