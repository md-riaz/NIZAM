<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\TimeCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimeConditionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        // Permissions are deny-by-default, so the acting user is granted the
        // time-condition abilities these cases exercise rather than relying
        // on a permissive fallback.
        $slugs = ['time_conditions.view', 'time_conditions.create', 'time_conditions.update', 'time_conditions.delete'];
        foreach ($slugs as $slug) {
            Permission::updateOrCreate(['slug' => $slug], ['module' => 'core']);
        }
        $this->user->grantPermissions($slugs);
    }

    public function test_user_without_permission_cannot_list_time_conditions(): void
    {
        $unprivileged = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/time-conditions")
            ->assertForbidden();
    }

    public function test_can_list_time_conditions_for_a_organization(): void
    {
        TimeCondition::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/time-conditions");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_time_condition(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/time-conditions", [
                'name' => 'Business Hours',
                'conditions' => [
                    ['wday' => 'mon-fri', 'time_from' => '09:00', 'time_to' => '17:00'],
                ],
                'match_destination_type' => 'extension',
                'match_destination_id' => Str::uuid()->toString(),
                'no_match_destination_type' => 'voicemail',
                'no_match_destination_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('time_conditions', [
            'organization_id' => $this->organization->id,
            'name' => 'Business Hours',
        ]);
    }

    public function test_can_show_a_time_condition(): void
    {
        $tc = TimeCondition::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/time-conditions/{$tc->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $tc->name]);
    }

    public function test_can_update_a_time_condition(): void
    {
        $tc = TimeCondition::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/time-conditions/{$tc->id}", [
                'name' => 'Updated Hours',
                'conditions' => [
                    ['wday' => 'mon-sat', 'time_from' => '08:00', 'time_to' => '18:00'],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('time_conditions', [
            'id' => $tc->id,
            'name' => 'Updated Hours',
        ]);
    }

    public function test_can_delete_a_time_condition(): void
    {
        $tc = TimeCondition::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/time-conditions/{$tc->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('time_conditions', ['id' => $tc->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/time-conditions", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_returns_404_for_wrong_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $tc = TimeCondition::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/time-conditions/{$tc->id}");

        $response->assertStatus(404);
    }
}
