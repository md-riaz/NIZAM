<?php

namespace Tests\Unit\Observers;

use App\Models\Schedule;
use App\Models\Tenant;
use App\Services\TenantManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $schedule = Schedule::create([
            'tenant_id' => $tenant->id,
            'name' => 'Business Hours',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }

    public function test_schedule_updated_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $schedule->update(['name' => 'Updated Schedule']);

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'name' => 'Updated Schedule',
        ]);
    }

    public function test_schedule_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $schedule->delete();

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }
}
