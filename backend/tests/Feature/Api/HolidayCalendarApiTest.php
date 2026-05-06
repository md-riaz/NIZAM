<?php

namespace Tests\Feature\Api;

use App\Models\HolidayCalendar;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Models\Schedule;
use App\Models\User;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayCalendarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_holidays_rebuilds_manifest_without_stale_holiday_condition(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'holiday-manifest.example.com',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $calendar = HolidayCalendar::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Company Holidays',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $calendar->holidays()->create([
            'name' => 'Founders Day',
            'holiday_date' => '2026-12-16',
            'is_active' => true,
        ]);
        $schedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Business Hours',
            'timezone' => 'UTC',
            'is_active' => true,
            'holiday_calendar_id' => $calendar->id,
        ]);
        $schedule->rules()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        app(OrganizationManifestBuilder::class)->buildAndActivate($organization->fresh());

        $beforeManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($beforeManifest);
        $this->assertStringContainsString('schedule_'.$schedule->id.'_holiday', $beforeManifest->content);
        $this->assertStringContainsString('2026-12-16', $beforeManifest->content);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/holiday-calendars/{$calendar->id}", [
                'name' => 'Company Holidays',
                'timezone' => 'UTC',
                'is_active' => true,
                'holidays' => [],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $calendar->id)
            ->assertJsonCount(0, 'data.holidays');

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('schedule_'.$schedule->id.'_holiday', $afterManifest->content);
        $this->assertStringNotContainsString('2026-12-16', $afterManifest->content);
    }

    public function test_deleting_holiday_rebuilds_manifest_without_stale_holiday_condition(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'holiday-manifest.example.com',
        ]);
        $calendar = HolidayCalendar::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Company Holidays',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $holiday = $calendar->holidays()->create([
            'name' => 'Founders Day',
            'holiday_date' => '2026-12-16',
            'is_active' => true,
        ]);
        $schedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Business Hours',
            'timezone' => 'UTC',
            'is_active' => true,
            'holiday_calendar_id' => $calendar->id,
        ]);
        $schedule->rules()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        app(OrganizationManifestBuilder::class)->buildAndActivate($organization->fresh());

        $beforeManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($beforeManifest);
        $this->assertStringContainsString('schedule_'.$schedule->id.'_holiday', $beforeManifest->content);
        $this->assertStringContainsString('2026-12-16', $beforeManifest->content);

        $holiday->delete();

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('schedule_'.$schedule->id.'_holiday', $afterManifest->content);
        $this->assertStringNotContainsString('2026-12-16', $afterManifest->content);
    }
}
