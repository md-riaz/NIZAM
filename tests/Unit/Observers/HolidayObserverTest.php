<?php

namespace Tests\Unit\Observers;

use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\Tenant;
use App\Observers\HolidayObserver;
use App\Services\TenantManifestBuilder;
use Mockery;
use Tests\TestCase;

class HolidayObserverTest extends TestCase
{
    public function test_holiday_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $calendar = HolidayCalendar::factory()->create(['tenant_id' => $tenant->id]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new HolidayObserver($builder);
        $holiday = Holiday::factory()->create([
            'holiday_calendar_id' => $calendar->id,
            'holiday_date' => '2026-12-25',
        ]);

        $this->assertEquals($calendar->id, $holiday->holiday_calendar_id);
    }

    public function test_holiday_updated_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $calendar = HolidayCalendar::factory()->create(['tenant_id' => $tenant->id]);
        $holiday = Holiday::factory()->create([
            'holiday_calendar_id' => $calendar->id,
            'holiday_date' => '2026-12-25',
        ]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new HolidayObserver($builder);
        $holiday->update(['name' => 'Updated Holiday']);

        $this->assertEquals('Updated Holiday', $holiday->fresh()->name);
    }

    public function test_holiday_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $calendar = HolidayCalendar::factory()->create(['tenant_id' => $tenant->id]);
        $holiday = Holiday::factory()->create([
            'holiday_calendar_id' => $calendar->id,
            'holiday_date' => '2026-12-25',
        ]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new HolidayObserver($builder);
        $holiday->delete();

        $this->assertNull(Holiday::find($holiday->id));
    }
}
