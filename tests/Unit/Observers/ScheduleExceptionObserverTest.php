<?php

namespace Tests\Unit\Observers;

use App\Models\ScheduleException;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Observers\ScheduleExceptionObserver;
use App\Services\TenantManifestBuilder;
use Mockery;
use Tests\TestCase;

class ScheduleExceptionObserverTest extends TestCase
{
    public function test_schedule_exception_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleExceptionObserver();
        $observer->created(
            ScheduleException::factory()->create([
                'schedule_id' => $schedule->id,
                'start_datetime' => '2026-12-25 09:00:00',
                'end_datetime' => '2026-12-25 17:00:00',
                'state' => 'open',
            ])
        );

        $this->assertEquals($schedule->id, $schedule->id);
    }

    public function test_schedule_exception_updated_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $exception = ScheduleException::factory()->create([
            'schedule_id' => $schedule->id,
            'start_datetime' => '2026-12-25 09:00:00',
            'end_datetime' => '2026-12-25 17:00:00',
            'state' => 'open',
        ]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleExceptionObserver();
        $exception->update(['state' => 'closed']);

        $this->assertEquals('closed', $exception->fresh()->state);
    }

    public function test_schedule_exception_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $exception = ScheduleException::factory()->create([
            'schedule_id' => $schedule->id,
            'start_datetime' => '2026-12-25 09:00:00',
            'end_datetime' => '2026-12-25 17:00:00',
            'state' => 'open',
        ]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleExceptionObserver();
        $exception->delete();

        $this->assertNull(ScheduleException::find($exception->id));
    }
}
