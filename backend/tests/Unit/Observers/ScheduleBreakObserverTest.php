<?php

namespace Tests\Unit\Observers;

use App\Models\ScheduleBreak;
use App\Models\Schedule;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleBreakObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_break_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

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
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);
        $break = ScheduleBreak::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $break->update(['start_time' => '12:30']);

        $this->assertDatabaseHas('schedule_breaks', [
            'id' => $break->id,
            'start_time' => '12:30',
        ]);
    }

    public function test_schedule_break_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);
        $break = ScheduleBreak::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $break->delete();

        $this->assertDatabaseMissing('schedule_breaks', ['id' => $break->id]);
    }
}
