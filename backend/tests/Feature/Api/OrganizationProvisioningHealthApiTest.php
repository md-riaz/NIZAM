<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\HolidayCalendar;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Models\Permission;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationProvisioningHealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_organization_provisioning_health(): void
    {
        $organization = $this->makeProvisionedOrganization();
        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/provisioning-health");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.summary', 'Organization provisioning is ready.')
            ->assertJsonPath('data.warning_count', 0)
            ->assertJsonPath('data.blocker_count', 0)
            ->assertJsonPath('data.next_actions', []);

        $response->assertJsonCount(5, 'data.checks');
        $response->assertJsonFragment([
            'key' => 'default_schedule',
            'status' => 'ok',
            'message' => 'Default schedule is active.',
        ]);
    }

    public function test_same_organization_admin_can_view_organization_provisioning_health(): void
    {
        $organization = $this->makeProvisionedOrganization();
        $user = User::factory()->create([
            'role' => 'admin',
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/provisioning-health");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.summary', 'Organization provisioning is ready.')
            ->assertJsonPath('data.warning_count', 0)
            ->assertJsonPath('data.blocker_count', 0)
            ->assertJsonPath('data.next_actions', []);
    }

    public function test_same_organization_agent_can_view_organization_provisioning_health(): void
    {
        $organization = $this->makeProvisionedOrganization();
        $user = User::factory()->create([
            'role' => 'agent',
            'organization_id' => $organization->id,
        ]);

        // Permissions are deny-by-default; this endpoint reuses the
        // organization "view" ability.
        Permission::updateOrCreate(['slug' => 'organizations.view'], ['module' => 'core']);
        $user->grantPermissions(['organizations.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/provisioning-health");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_agent_without_permission_cannot_view_organization_provisioning_health(): void
    {
        $organization = $this->makeProvisionedOrganization();
        $user = User::factory()->create([
            'role' => 'agent',
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/provisioning-health");

        $response->assertForbidden();
    }

    public function test_other_organization_user_is_blocked_by_organization_access(): void
    {
        $organization = $this->makeProvisionedOrganization();
        $otherOrganization = Organization::factory()->create();
        $user = User::factory()->create([
            'role' => 'admin',
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/provisioning-health");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden.',
            ]);
    }

    public function test_endpoint_returns_detailed_health_payload_for_blocked_org(): void
    {
        $organization = $this->makeProvisionedOrganization();
        $organization->dids()->delete();
        $organization->update([
            'default_holiday_calendar_id' => null,
        ]);
        $organization->defaultSchedule()->update(['holiday_calendar_id' => null]);

        $user = User::factory()->create([
            'role' => 'superadmin',
            'organization_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/provisioning-health");

        $response->assertOk()
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('data.summary', 'Organization provisioning is blocked.')
            ->assertJsonPath('data.warning_count', 1)
            ->assertJsonPath('data.blocker_count', 1);

        $this->assertEqualsCanonicalizing(
            ['Assign main DID', 'Select office preset'],
            $response->json('data.next_actions'),
        );

        $response->assertJsonFragment([
            'key' => 'entrypoint_did',
            'status' => 'blocked',
            'message' => 'Default entrypoint DID is missing.',
        ]);
        $response->assertJsonFragment([
            'key' => 'default_holiday_calendar',
            'status' => 'warning',
            'message' => 'Default holiday calendar is not selected.',
        ]);
    }

    private function makeProvisionedOrganization(): Organization
    {
        $organization = Organization::factory()->create([
            'domain' => 'health.example.test',
        ]);

        $holidayCalendar = HolidayCalendar::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Default Holidays',
            'is_active' => true,
        ]);

        $schedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'holiday_calendar_id' => $holidayCalendar->id,
            'name' => 'Main Business Hours',
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Main Business Phone',
        ]);

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1000',
            'is_active' => true,
        ]);

        $organization->update([
            'default_schedule_id' => $schedule->id,
            'default_holiday_calendar_id' => $holidayCalendar->id,
            'settings' => [
                'business_phone' => [
                    'default_entrypoint' => [
                        'flow_id' => (string) $flow->id,
                        'schedule_id' => (string) $schedule->id,
                        'open_target_type' => 'extension',
                        'open_target_id' => (string) $extension->id,
                        'provisioned' => true,
                    ],
                ],
            ],
        ]);

        Did::query()->create([
            'organization_id' => $organization->id,
            'number' => '+15550001111',
            'description' => 'Default Business Phone Entrypoint',
            'destination_type' => 'flow',
            'destination_id' => $flow->id,
            'is_active' => true,
        ]);

        OrganizationDialplanManifest::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'manifest_type' => 'inbound_routing',
                'is_active' => true,
            ],
            [
                'content' => '<document />',
                'checksum' => md5('<document />'),
            ],
        );

        return $organization->fresh();
    }
}
