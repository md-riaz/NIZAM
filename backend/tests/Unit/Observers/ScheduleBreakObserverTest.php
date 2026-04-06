<?php

namespace Tests\Unit\Observers;

use App\Models\ScheduleBreak;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Services\TenantManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleBreakObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_break_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $break = ScheduleBreak::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => 1,
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

        $this->assertDatabaseHas('schedule_breaks', ['id' => $break->id]);
    }

    public function test_schedule_break_updated_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $break = ScheduleBreak::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $break->update(['start_time' => '12:30']);

        $this->assertDatabaseHas('schedule_breaks', [
            'id' => $break->id,
            'start_time' => '12:30',
        ]);
    }

    public function test_schedule_break_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $break = ScheduleBreak::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $break->delete();

        $this->assertDatabaseMissing('schedule_breaks', ['id' => $break->id]);
    }
}
