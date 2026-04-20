<?php

namespace Tests\Unit\Observers;

use App\Models\ScheduleException;
use App\Models\Schedule;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleExceptionObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_exception_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $exception = ScheduleException::create([
            'schedule_id' => $schedule->id,
            'start_datetime' => '2026-12-25 09:00:00',
            'end_datetime' => '2026-12-25 17:00:00',
            'state' => 'open',
        ]);

        $this->assertDatabaseHas('schedule_exceptions', ['id' => $exception->id]);
    }

    public function test_schedule_exception_updated_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);
        $exception = ScheduleException::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $exception->update(['state' => 'closed']);

        $this->assertDatabaseHas('schedule_exceptions', [
            'id' => $exception->id,
            'state' => 'closed',
        ]);
    }

    public function test_schedule_exception_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);
        $exception = ScheduleException::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $exception->delete();

        $this->assertDatabaseMissing('schedule_exceptions', ['id' => $exception->id]);
    }
}
