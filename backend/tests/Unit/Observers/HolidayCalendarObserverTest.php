<?php

namespace Tests\Unit\Observers;

use App\Models\HolidayCalendar;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayCalendarObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_calendar_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $calendar = HolidayCalendar::create([
            'organization_id' => $organization->id,
            'name' => 'Company Holidays',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('holiday_calendars', ['id' => $calendar->id]);
    }

    public function test_holiday_calendar_updated_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $calendar = HolidayCalendar::factory()->create(['organization_id' => $organization->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $calendar->update(['name' => 'Updated Calendar']);

        $this->assertDatabaseHas('holiday_calendars', [
            'id' => $calendar->id,
            'name' => 'Updated Calendar',
        ]);
    }

    public function test_holiday_calendar_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $calendar = HolidayCalendar::factory()->create(['organization_id' => $organization->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $calendar->delete();

        $this->assertDatabaseMissing('holiday_calendars', ['id' => $calendar->id]);
    }
}
