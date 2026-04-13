<?php

namespace Tests\Unit\Services;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\DeviceProfile;
use App\Models\DeviceRegistrationSnapshot;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Presence\PresenceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private PresenceAggregator $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        $this->service = new PresenceAggregator;
    }

    public function test_it_merges_direct_and_extension_devices_and_prefers_primary_extension(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $primaryExtension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);
        $secondaryExtension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'is_primary' => false,
        ]);

        $directDevice = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'extension_id' => null,
            'is_active' => true,
        ]);
        $extensionDevice = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'extension_id' => $primaryExtension->id,
            'is_active' => true,
        ]);
        DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'extension_id' => $secondaryExtension->id,
            'is_active' => false,
        ]);

        DeviceRegistrationSnapshot::factory()->create([
            'tenant_id' => $tenant->id,
            'extension_id' => $primaryExtension->id,
            'registration_key' => 'business-user-primary',
            'registered' => true,
            'observed_at' => now(),
        ]);

        $presence = $this->service->forUser($user->load(['extensions.deviceProfiles', 'extensions.deviceRegistrationSnapshots', 'deviceProfiles']));

        $this->assertSame('available', $presence['status']);
        $this->assertSame('available', $presence['availability']);
        $this->assertSame($primaryExtension->id, $presence['primary_extension_id']);
        $this->assertSame(1, $presence['registered_device_count']);
        $this->assertTrue($presence['supports_softphone']);
        $deviceIds = array_column($presence['devices'], 'id');
        sort($deviceIds);

        $expectedDeviceIds = [$directDevice->id, $extensionDevice->id];
        sort($expectedDeviceIds);

        $this->assertEquals($expectedDeviceIds, array_intersect($deviceIds, $expectedDeviceIds));
        $this->assertCount(3, $presence['devices']);
        $this->assertContains(false, array_column($presence['devices'], 'is_active'));
    }

    public function test_it_reports_ringing_when_any_active_attempt_is_ringing(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'is_primary' => true,
        ]);
        $callSession = CallSession::factory()->create(['tenant_id' => $tenant->id]);
        $endpointBinding = EndpointBinding::factory()->forExtension($extension)->create();
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $callSession->id,
            'endpoint_binding_id' => $endpointBinding->id,
            'status' => CallDeliveryAttempt::STATUS_RINGING,
            'attempt_type' => CallDeliveryAttempt::TYPE_SIP,
            'started_at' => now()->subSeconds(5),
        ]);

        $presence = $this->service->forUser($user->load('extensions'), [$attempt]);

        $this->assertSame('ringing', $presence['status']);
        $this->assertSame('engaged', $presence['availability']);
        $this->assertSame(1, $presence['active_call_count']);
        $this->assertSame($attempt->id, $presence['active_calls'][0]['id']);
    }

    public function test_it_reports_on_call_for_non_ringing_active_attempts(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
        $callSession = CallSession::factory()->create(['tenant_id' => $tenant->id]);
        $endpointBinding = EndpointBinding::factory()->forExtension($extension)->create();
        $attempt = CallDeliveryAttempt::factory()->create([
            'call_session_id' => $callSession->id,
            'endpoint_binding_id' => $endpointBinding->id,
            'status' => CallDeliveryAttempt::STATUS_CONFIRMED,
            'attempt_type' => CallDeliveryAttempt::TYPE_PUSH,
        ]);

        $presence = $this->service->forUser($user->load('extensions'), [$attempt]);

        $this->assertSame('on_call', $presence['status']);
        $this->assertSame('engaged', $presence['availability']);
    }

    public function test_it_falls_back_to_offline_when_no_registrations_or_active_calls_exist(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'user',
        ]);
        Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $presence = $this->service->forUser($user->load('extensions'), []);

        $this->assertSame('offline', $presence['status']);
        $this->assertSame('offline', $presence['availability']);
        $this->assertSame(0, $presence['registered_device_count']);
        $this->assertFalse($presence['supports_softphone']);
    }

    public function test_device_profile_can_resolve_user_from_direct_or_extension_mapping(): void
    {
        $tenant = Tenant::factory()->create();
        $directUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $extensionUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $extensionUser->id,
        ]);

        $directDevice = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $directUser->id,
            'extension_id' => null,
        ]);
        $extensionDevice = DeviceProfile::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'extension_id' => $extension->id,
        ]);

        $this->assertTrue($directDevice->resolvedUser()->is($directUser));
        $this->assertTrue($extensionDevice->resolvedUser()->is($extensionUser));
        $this->assertTrue($extension->user->is($extensionUser));
        $this->assertTrue($extensionUser->extensions()->first()->is($extension));
    }
}
