<?php

namespace Tests\Unit\Observers;

use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $calendar = HolidayCalendar::factory()->create(['organization_id' => $organization->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $holiday = Holiday::create([
            'holiday_calendar_id' => $calendar->id,
            'name' => 'New Year',
            'holiday_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('holidays', ['id' => $holiday->id]);
    }

    public function test_holiday_updated_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $calendar = HolidayCalendar::factory()->create(['organization_id' => $organization->id]);
        $holiday = Holiday::factory()->create(['holiday_calendar_id' => $calendar->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $holiday->update(['name' => 'Updated Holiday']);

        $this->assertDatabaseHas('holidays', [
            'id' => $holiday->id,
            'name' => 'Updated Holiday',
        ]);
    }

    public function test_holiday_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $calendar = HolidayCalendar::factory()->create(['organization_id' => $organization->id]);
        $holiday = Holiday::factory()->create(['holiday_calendar_id' => $calendar->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $holiday->delete();

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }
}
