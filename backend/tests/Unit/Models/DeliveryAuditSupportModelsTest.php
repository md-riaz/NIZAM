<?php

namespace Tests\Unit\Models;

use App\Models\CallSession;
use App\Models\DeviceRegistrationSnapshot;
use App\Models\EndpointBinding;
use App\Models\Extension;
use App\Models\PushNotificationLog;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAuditSupportModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_notification_log_casts_payload_and_relates_to_session_and_endpoint(): void
    {
        $callSession = CallSession::factory()->create();
        $endpointBinding = EndpointBinding::factory()->create(['organization_id' => $callSession->organization_id]);

        $log = PushNotificationLog::factory()->create([
            'call_session_id' => $callSession->id,
            'endpoint_binding_id' => $endpointBinding->id,
            'response_payload' => ['provider' => 'apns', 'accepted' => true],
        ]);

        $log->refresh();

        $this->assertSame(['provider' => 'apns', 'accepted' => true], $log->response_payload);
        $this->assertTrue($log->callSession->is($callSession));
        $this->assertTrue($log->endpointBinding->is($endpointBinding));
        $this->assertTrue($callSession->pushNotificationLogs()->first()->is($log));
        $this->assertTrue($endpointBinding->pushNotificationLogs()->first()->is($log));
    }

    public function test_device_registration_snapshot_casts_fields_and_relates_to_organization_endpoint_and_extension(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $endpointBinding = EndpointBinding::factory()->create([
            'organization_id' => $organization->id,
            'extension_id' => $extension->id,
        ]);

        $snapshot = DeviceRegistrationSnapshot::factory()->create([
            'organization_id' => $organization->id,
            'endpoint_binding_id' => $endpointBinding->id,
            'extension_id' => $extension->id,
            'registered' => false,
            'registration_key' => 'organization-aor-1001',
        ]);

        $snapshot->refresh();

        $this->assertFalse($snapshot->registered);
        $this->assertSame('organization-aor-1001', $snapshot->registration_key);
        $this->assertTrue($snapshot->organization->is($organization));
        $this->assertTrue($snapshot->endpointBinding->is($endpointBinding));
        $this->assertTrue($snapshot->extension->is($extension));
        $this->assertTrue($organization->deviceRegistrationSnapshots()->first()->is($snapshot));
        $this->assertTrue($endpointBinding->deviceRegistrationSnapshots()->first()->is($snapshot));
        $this->assertTrue($extension->deviceRegistrationSnapshots()->first()->is($snapshot));
    }

    public function test_registration_snapshot_allows_audit_entries_without_endpoint_or_extension_links(): void
    {
        $organization = Organization::factory()->create();

        $snapshot = DeviceRegistrationSnapshot::factory()->create([
            'organization_id' => $organization->id,
            'endpoint_binding_id' => null,
            'extension_id' => null,
        ]);

        $this->assertNull($snapshot->endpointBinding);
        $this->assertNull($snapshot->extension);
        $this->assertTrue($snapshot->organization->is($organization));
    }
}
