<?php

namespace Tests\Unit\Observers;

use App\Models\Schedule;
use App\Models\Tenant;
use App\Observers\ScheduleObserver;
use App\Services\TenantManifestBuilder;
use Mockery;
use Tests\TestCase;

class ScheduleObserverTest extends TestCase
{
    public function test_schedule_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleObserver($builder);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertEquals($tenant->id, $schedule->tenant_id);
    }

    public function test_schedule_updated_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleObserver($builder);
        $schedule->update(['name' => 'Updated Schedule']);

        $this->assertEquals('Updated Schedule', $schedule->fresh()->name);
    }

    public function test_schedule_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleObserver($builder);
        $schedule->delete();

        $this->assertNull(Schedule::find($schedule->id));
    }
}
