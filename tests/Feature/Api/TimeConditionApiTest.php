<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\TimeCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimeConditionApiTest extends TestCase
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

    public function test_can_list_time_conditions_for_a_tenant(): void
    {
        TimeCondition::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/time-conditions");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_time_condition(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/time-conditions", [
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
            'tenant_id' => $this->tenant->id,
            'name' => 'Business Hours',
        ]);
    }

    public function test_can_show_a_time_condition(): void
    {
        $tc = TimeCondition::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/time-conditions/{$tc->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $tc->name]);
    }

    public function test_can_update_a_time_condition(): void
    {
        $tc = TimeCondition::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/tenants/{$this->tenant->id}/time-conditions/{$tc->id}", [
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
        $tc = TimeCondition::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/time-conditions/{$tc->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('time_conditions', ['id' => $tc->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/time-conditions", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_returns_404_for_wrong_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $tc = TimeCondition::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/time-conditions/{$tc->id}");

        $response->assertStatus(404);
    }
}
