<?php

namespace Tests\Feature\Api;

use App\Models\RingGroup;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RingGroupApiTest extends TestCase
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

    public function test_can_list_ring_groups_for_a_organization(): void
    {
        RingGroup::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/ring-groups");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_ring_group(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/ring-groups", [
                'name' => 'Sales Team',
                'strategy' => 'simultaneous',
                'ring_timeout' => 30,
                'members' => [Str::uuid()->toString(), Str::uuid()->toString()],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ring_groups', [
            'organization_id' => $this->organization->id,
            'name' => 'Sales Team',
        ]);
    }

    public function test_can_show_a_ring_group(): void
    {
        $ringGroup = RingGroup::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/ring-groups/{$ringGroup->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $ringGroup->name]);
    }

    public function test_can_update_a_ring_group(): void
    {
        $ringGroup = RingGroup::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/ring-groups/{$ringGroup->id}", [
                'name' => 'Updated Team',
                'members' => [Str::uuid()->toString()],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('ring_groups', [
            'id' => $ringGroup->id,
            'name' => 'Updated Team',
        ]);
    }

    public function test_can_delete_a_ring_group(): void
    {
        $ringGroup = RingGroup::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/ring-groups/{$ringGroup->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('ring_groups', ['id' => $ringGroup->id]);
    }
}
