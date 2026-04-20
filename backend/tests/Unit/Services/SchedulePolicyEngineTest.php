<?php

namespace Tests\Unit\Services;

use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Team;
use App\Models\Organization;
use App\Models\User;
use App\Services\Policy\SchedulePolicyEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulePolicyEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_organization_default_schedule_for_organization_open_state(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        ScheduleRule::factory()->create([
            'schedule_id' => $schedule->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $organization->update(['default_schedule_id' => $schedule->id]);

        $result = app(SchedulePolicyEngine::class)->evaluate($organization, '2026-05-18 10:00:00 UTC');

        $this->assertTrue($result['open']);
        $this->assertSame('open', $result['state']);
        $this->assertTrue($result['schedule']->is($schedule));
        $this->assertSame('organization', $result['inherited_from']);
    }

    public function test_team_schedule_override_can_use_its_own_holiday_calendar(): void
    {
        $organization = Organization::factory()->create();
        $defaultCalendar = HolidayCalendar::factory()->create(['organization_id' => $organization->id]);
        $teamCalendar = HolidayCalendar::factory()->create(['organization_id' => $organization->id]);
        $defaultSchedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'holiday_calendar_id' => $defaultCalendar->id,
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $teamSchedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        ScheduleRule::factory()->create([
            'schedule_id' => $defaultSchedule->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        ScheduleRule::factory()->create([
            'schedule_id' => $teamSchedule->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        Holiday::factory()->create([
            'holiday_calendar_id' => $teamCalendar->id,
            'holiday_date' => '2026-05-18',
            'is_active' => true,
        ]);

        $organization->update([
            'default_schedule_id' => $defaultSchedule->id,
            'default_holiday_calendar_id' => $defaultCalendar->id,
        ]);

        $team = Team::create([
            'organization_id' => $organization->id,
            'schedule_id' => $teamSchedule->id,
            'holiday_calendar_id' => $teamCalendar->id,
            'name' => 'Support',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        $result = app(SchedulePolicyEngine::class)->evaluate($team, '2026-05-18 10:00:00 UTC');

        $this->assertSame('team', $result['inherited_from']);
        $this->assertNotNull($result['holiday_calendar']);
        $this->assertTrue($result['holiday_calendar']->is($teamCalendar));
        $this->assertFalse($result['open']);
        $this->assertSame('holiday', $result['state']);
        $this->assertTrue($result['schedule']->is($teamSchedule));
    }

    public function test_user_schedule_override_takes_precedence_over_organization_default(): void
    {
        $organization = Organization::factory()->create();
        $defaultSchedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $userSchedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        ScheduleRule::factory()->create([
            'schedule_id' => $defaultSchedule->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
        ScheduleRule::factory()->create([
            'schedule_id' => $userSchedule->id,
            'day_of_week' => 1,
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
        ]);

        $organization->update(['default_schedule_id' => $defaultSchedule->id]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'schedule_id' => $userSchedule->id,
        ]);

        $result = app(SchedulePolicyEngine::class)->evaluate($user, '2026-05-18 10:00:00 UTC');

        $this->assertFalse($result['open']);
        $this->assertSame('closed', $result['state']);
        $this->assertTrue($result['schedule']->is($userSchedule));
        $this->assertSame('user', $result['inherited_from']);
    }

    public function test_is_open_now_returns_false_when_target_has_no_effective_schedule(): void
    {
        $organization = Organization::factory()->create();

        $this->assertFalse(app(SchedulePolicyEngine::class)->isOpenNow($organization, '2026-05-18 10:00:00 UTC'));
    }
}
