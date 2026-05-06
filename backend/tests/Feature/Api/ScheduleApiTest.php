<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Models\Schedule;
use App\Models\User;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_schedule_rules_rebuilds_manifest_without_stale_open_rule(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'schedule-manifest.example.com',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $schedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Business Hours',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $schedule->rules()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        app(OrganizationManifestBuilder::class)->buildAndActivate($organization->fresh());

        $beforeManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($beforeManifest);
        $this->assertStringContainsString('field="wday" expression="^1$"', $beforeManifest->content);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/organizations/{$organization->id}/schedules/{$schedule->id}", [
                'name' => 'Business Hours',
                'timezone' => 'UTC',
                'is_active' => true,
                'rules' => [],
                'breaks' => [],
                'exceptions' => [],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $schedule->id);

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('field="wday" expression="^1$"', $afterManifest->content);
    }

    public function test_deleting_schedule_rule_rebuilds_manifest_without_stale_open_rule(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'schedule-manifest.example.com',
        ]);
        $schedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Business Hours',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $rule = $schedule->rules()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        app(OrganizationManifestBuilder::class)->buildAndActivate($organization->fresh());

        $beforeManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($beforeManifest);
        $this->assertStringContainsString('field="wday" expression="^1$"', $beforeManifest->content);

        $rule->delete();

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('field="wday" expression="^1$"', $afterManifest->content);
    }
}
