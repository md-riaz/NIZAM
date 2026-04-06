<?php

namespace Tests\Feature\Api;

use App\Models\RingGroup;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RingGroupApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_list_ring_groups_for_a_tenant(): void
    {
        RingGroup::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/ring-groups");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_ring_group(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/ring-groups", [
                'name' => 'Sales Team',
                'strategy' => 'simultaneous',
                'ring_timeout' => 30,
                'members' => [Str::uuid()->toString(), Str::uuid()->toString()],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ring_groups', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Sales Team',
        ]);
    }

    public function test_can_show_a_ring_group(): void
    {
        $ringGroup = RingGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/ring-groups/{$ringGroup->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $ringGroup->name]);
    }

    public function test_can_update_a_ring_group(): void
    {
        $ringGroup = RingGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tenants/{$this->tenant->id}/ring-groups/{$ringGroup->id}", [
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
        $ringGroup = RingGroup::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/ring-groups/{$ringGroup->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('ring_groups', ['id' => $ringGroup->id]);
    }
}
