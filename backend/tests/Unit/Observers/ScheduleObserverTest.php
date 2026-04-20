<?php

namespace Tests\Unit\Observers;

use App\Models\Schedule;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $schedule = Schedule::create([
            'organization_id' => $organization->id,
            'name' => 'Business Hours',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }

    public function test_schedule_updated_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $schedule->update(['name' => 'Updated Schedule']);

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'name' => 'Updated Schedule',
        ]);
    }

    public function test_schedule_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $schedule->delete();

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }
}
