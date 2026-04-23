<?php

namespace Tests\Feature\Api;

use App\Models\HolidayCalendar;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_can_show_team_with_inbound_routing_fields(): void
    {
        $holidayCalendar = HolidayCalendar::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $schedule = Schedule::factory()->create([
            'organization_id' => $this->organization->id,
            'holiday_calendar_id' => $holidayCalendar->id,
        ]);
        $team = Team::create([
            'organization_id' => $this->organization->id,
            'schedule_id' => $schedule->id,
            'holiday_calendar_id' => $holidayCalendar->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/teams/{$team->id}");

        $response->assertOk()
            ->assertJsonPath('data.schedule_id', $schedule->id)
            ->assertJsonPath('data.holiday_calendar_id', $holidayCalendar->id)
            ->assertJsonPath('data.name', 'Sales');
    }

    public function test_can_create_team_with_inbound_routing_fields(): void
    {
        $holidayCalendar = HolidayCalendar::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $schedule = Schedule::factory()->create([
            'organization_id' => $this->organization->id,
            'holiday_calendar_id' => $holidayCalendar->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/teams", [
                'schedule_id' => $schedule->id,
                'holiday_calendar_id' => $holidayCalendar->id,
                'name' => 'Support',
                'strategy' => 'priority',
                'timeout' => 20,
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.schedule_id', $schedule->id)
            ->assertJsonPath('data.holiday_calendar_id', $holidayCalendar->id);

        $this->assertDatabaseHas('teams', [
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'schedule_id' => $schedule->id,
            'holiday_calendar_id' => $holidayCalendar->id,
        ]);
    }

    public function test_rejects_team_create_with_schedule_from_other_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $foreignSchedule = Schedule::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/teams", [
                'schedule_id' => $foreignSchedule->id,
                'name' => 'Support',
                'strategy' => 'priority',
                'timeout' => 20,
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_id']);
    }
}
