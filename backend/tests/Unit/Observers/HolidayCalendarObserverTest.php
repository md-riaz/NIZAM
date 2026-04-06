<?php

namespace Tests\Unit\Observers;

use App\Models\HolidayCalendar;
use App\Models\Tenant;
use App\Services\TenantManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayCalendarObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_calendar_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $calendar = HolidayCalendar::create([
            'tenant_id' => $tenant->id,
            'name' => 'Company Holidays',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('holiday_calendars', ['id' => $calendar->id]);
    }

    public function test_holiday_calendar_updated_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();
        $calendar = HolidayCalendar::factory()->create(['tenant_id' => $tenant->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $calendar->update(['name' => 'Updated Calendar']);

        $this->assertDatabaseHas('holiday_calendars', [
            'id' => $calendar->id,
            'name' => 'Updated Calendar',
        ]);
    }

    public function test_holiday_calendar_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();
        $calendar = HolidayCalendar::factory()->create(['tenant_id' => $tenant->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $calendar->delete();

        $this->assertDatabaseMissing('holiday_calendars', ['id' => $calendar->id]);
    }
}
