<?php

namespace Tests\Unit\Services;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\HolidayCalendar;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Models\Schedule;
use App\Services\Organization\OrganizationProvisioningHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationProvisioningHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_org_missing_entrypoint_did_is_blocked(): void
    {
        $organization = $this->makeProvisionedOrganization();

        $organization->dids()->delete();

        $health = app(OrganizationProvisioningHealthService::class)->evaluate($organization->fresh());

        $this->assertSame('blocked', $health['status']);
        $this->assertSame(1, $health['blocker_count']);
        $this->assertSame(0, $health['warning_count']);
        $this->assertCheckStatus($health['checks'], 'entrypoint_did', 'blocked');
        $this->assertContains('Assign main DID', $health['next_actions']);
    }

    public function test_org_missing_default_schedule_is_blocked(): void
    {
        $organization = $this->makeProvisionedOrganization();

        $organization->update(['default_schedule_id' => null]);

        $health = app(OrganizationProvisioningHealthService::class)->evaluate($organization->fresh());

        $this->assertSame('blocked', $health['status']);
        $this->assertSame(1, $health['blocker_count']);
        $this->assertSame(0, $health['warning_count']);
        $this->assertCheckStatus($health['checks'], 'default_schedule', 'blocked');
        $this->assertContains('Configure main business hours', $health['next_actions']);
    }

    public function test_org_missing_default_holiday_calendar_is_warning(): void
    {
        $organization = $this->makeProvisionedOrganization();
        $organization->defaultSchedule->update(['holiday_calendar_id' => null]);
        $organization->update(['default_holiday_calendar_id' => null]);

        $health = app(OrganizationProvisioningHealthService::class)->evaluate($organization->fresh());

        $this->assertSame('warning', $health['status']);
        $this->assertSame(0, $health['blocker_count']);
        $this->assertSame(1, $health['warning_count']);
        $this->assertCheckStatus($health['checks'], 'default_holiday_calendar', 'warning');
        $this->assertContains('Select office preset', $health['next_actions']);
    }

    public function test_org_with_inactive_destination_target_is_blocked(): void
    {
        $organization = $this->makeProvisionedOrganization();

        $organization->extensions()->firstOrFail()->update(['is_active' => false]);

        $health = app(OrganizationProvisioningHealthService::class)->evaluate($organization->fresh());

        $this->assertSame('blocked', $health['status']);
        $this->assertSame(1, $health['blocker_count']);
        $this->assertCheckStatus($health['checks'], 'open_target', 'blocked');
        $this->assertContains('Choose an active main destination', $health['next_actions']);
    }

    public function test_org_with_active_manifest_and_no_warnings_is_ready(): void
    {
        $organization = $this->makeProvisionedOrganization();

        $health = app(OrganizationProvisioningHealthService::class)->evaluate($organization->fresh());

        $this->assertSame('ready', $health['status']);
        $this->assertSame('Organization provisioning is ready.', $health['summary']);
        $this->assertSame(0, $health['blocker_count']);
        $this->assertSame(0, $health['warning_count']);
        $this->assertCheckStatus($health['checks'], 'default_schedule', 'ok');
        $this->assertCheckStatus($health['checks'], 'default_holiday_calendar', 'ok');
        $this->assertCheckStatus($health['checks'], 'entrypoint_did', 'ok');
        $this->assertCheckStatus($health['checks'], 'open_target', 'ok');
        $this->assertCheckStatus($health['checks'], 'active_manifest', 'ok');
        $this->assertSame([], $health['next_actions']);
    }

    /**
     * @param  list<array{key:string,status:string,message:string}>  $checks
     */
    private function assertCheckStatus(array $checks, string $key, string $status): void
    {
        $check = collect($checks)->firstWhere('key', $key);

        $this->assertNotNull($check, sprintf('Missing check with key [%s].', $key));
        $this->assertSame($status, $check['status']);
        $this->assertNotSame('', trim($check['message']));
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

        return $organization->fresh([
            'defaultSchedule',
            'defaultHolidayCalendar',
            'dids',
            'extensions',
            'teams',
            'flows',
        ]);
    }
}
