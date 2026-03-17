<?php

namespace Tests\Unit\Observers;

use App\Models\ScheduleRule;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Services\TenantManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleRuleObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_rule_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

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
        $tenant = Tenant::factory()->create();
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $rule = ScheduleRule::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $rule->update(['start_time' => '08:30']);

        $this->assertDatabaseHas('schedule_rules', [
            'id' => $rule->id,
            'start_time' => '08:30',
        ]);
    }

    public function test_schedule_rule_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create();
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $rule = ScheduleRule::factory()->create(['schedule_id' => $schedule->id]);

        $builder = $this->mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($tenant));

        $rule->delete();

        $this->assertDatabaseMissing('schedule_rules', ['id' => $rule->id]);
    }
}
