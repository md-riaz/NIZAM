<?php

namespace Tests\Unit\Observers;

use App\Models\ScheduleRule;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Observers\ScheduleRuleObserver;
use App\Services\TenantManifestBuilder;
use Mockery;
use Tests\TestCase;

class ScheduleRuleObserverTest extends TestCase
{
    public function test_schedule_rule_created_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleRuleObserver();
        $observer->created(
            ScheduleRule::factory()->create([
                'schedule_id' => $schedule->id,
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ])
        );

        $this->assertEquals($schedule->id, $schedule->id);
    }

    public function test_schedule_rule_updated_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $rule = ScheduleRule::factory()->create([
            'schedule_id' => $schedule->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleRuleObserver();
        $rule->update(['start_time' => '08:30']);

        $this->assertEquals('08:30', $rule->fresh()->start_time);
    }

    public function test_schedule_rule_deleted_triggers_manifest_rebuild(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $rule = ScheduleRule::factory()->create([
            'schedule_id' => $schedule->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $builder = Mockery::mock(TenantManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')
            ->once()
            ->with($tenant);

        $this->app->instance(TenantManifestBuilder::class, $builder);

        $observer = new ScheduleRuleObserver();
        $rule->delete();

        $this->assertNull(ScheduleRule::find($rule->id));
    }
}
