<?php

namespace Tests\Unit\Observers;

use App\Models\ScheduleBreak;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Observers\ScheduleBreakObserver;
use App\Services\TenantManifestBuilder;
use Mockery;
use Tests\TestCase;

class ScheduleBreakObserverTest extends TestCase
{
    public function test_schedule_break_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleBreakObserver();
        $observer->created(
            ScheduleBreak::factory()->create([
                'schedule_id' => $schedule->id,
                'day_of_week' => 1,
                'start_time' => '12:00',
                'end_time' => '13:00',
            ])
        );

        $this->assertEquals($schedule->id, $schedule->id);
    }

    public function test_schedule_break_updated_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $break = ScheduleBreak::factory()->create([
            'schedule_id' => $schedule->id,
            'day_of_week' => 1,
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleBreakObserver();
        $break->update(['start_time' => '12:30']);

        $this->assertEquals('12:30', $break->fresh()->start_time);
    }

    public function test_schedule_break_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $break = ScheduleBreak::factory()->create([
            'schedule_id' => $schedule->id,
            'day_of_week' => 1,
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleBreakObserver();
        $break->delete();

        $this->assertNull(ScheduleBreak::find($break->id));
    }
}
