<?php

namespace Tests\Unit\Observers;

use App\Models\HolidayCalendar;
use App\Models\Tenant;
use App\Observers\HolidayCalendarObserver;
use App\Services\TenantManifestBuilder;
use Mockery;
use Tests\TestCase;

class HolidayCalendarObserverTest extends TestCase
{
    public function test_holiday_calendar_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new HolidayCalendarObserver($builder);
        $calendar = HolidayCalendar::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertEquals($tenant->id, $calendar->tenant_id);
    }

    public function test_holiday_calendar_updated_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $calendar = HolidayCalendar::factory()->create(['tenant_id' => $tenant->id]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new HolidayCalendarObserver($builder);
        $calendar->update(['name' => 'Updated Calendar']);

        $this->assertEquals('Updated Calendar', $calendar->fresh()->name);
    }

    public function test_holiday_calendar_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $calendar = HolidayCalendar::factory()->create(['tenant_id' => $tenant->id]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new HolidayCalendarObserver($builder);
        $calendar->delete();

        $this->assertNull(HolidayCalendar::find($calendar->id));
    }
}
