<?php

namespace Tests\Unit\Observers;

use App\Models\ScheduleRule;
use App\Models\Schedule;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleRuleObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_rule_created_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $rule = ScheduleRule::create([
            'schedule_id' => $schedule->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->assertDatabaseHas('schedule_rules', ['id' => $rule->id]);
    }

    public function test_schedule_rule_updated_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);
        $rule = ScheduleRule::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $rule->update(['start_time' => '08:30']);

        $this->assertDatabaseHas('schedule_rules', [
            'id' => $rule->id,
            'start_time' => '08:30',
        ]);
    }

    public function test_schedule_rule_deleted_triggers_manifest_rebuild(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create(['organization_id' => $organization->id]);
        $rule = ScheduleRule::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $rule->delete();

        $this->assertDatabaseMissing('schedule_rules', ['id' => $rule->id]);
    }
}
