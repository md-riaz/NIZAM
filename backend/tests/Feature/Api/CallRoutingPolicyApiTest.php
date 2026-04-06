<?php

namespace Tests\Feature\Api;

use App\Models\CallRoutingPolicy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallRoutingPolicyApiTest extends TestCase
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

    public function test_can_list_call_routing_policies_for_a_tenant(): void
    {
        CallRoutingPolicy::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-routing-policies");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_call_routing_policy(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/call-routing-policies", [
                'name' => 'Business Hours Policy',
                'description' => 'Route based on business hours',
                'conditions' => [
                    ['type' => 'time_of_day', 'params' => ['start' => '09:00', 'end' => '17:00']],
                ],
                'match_destination_type' => 'extension',
                'match_destination_id' => Str::uuid()->toString(),
                'no_match_destination_type' => 'voicemail',
                'no_match_destination_id' => Str::uuid()->toString(),
                'priority' => 10,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('call_routing_policies', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Business Hours Policy',
        ]);
    }

    public function test_can_show_a_call_routing_policy(): void
    {
        $policy = CallRoutingPolicy::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-routing-policies/{$policy->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $policy->name]);
    }

    public function test_can_update_a_call_routing_policy(): void
    {
        $policy = CallRoutingPolicy::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tenants/{$this->tenant->id}/call-routing-policies/{$policy->id}", [
                'name' => 'Updated Policy',
                'priority' => 50,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('call_routing_policies', [
            'id' => $policy->id,
            'name' => 'Updated Policy',
            'priority' => 50,
        ]);
    }

    public function test_can_delete_a_call_routing_policy(): void
    {
        $policy = CallRoutingPolicy::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/call-routing-policies/{$policy->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('call_routing_policies', ['id' => $policy->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/call-routing-policies", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'conditions', 'match_destination_type', 'match_destination_id']);
    }

    public function test_validates_condition_types(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/call-routing-policies", [
                'name' => 'Test',
                'conditions' => [
                    ['type' => 'invalid_type', 'params' => []],
                ],
                'match_destination_type' => 'extension',
                'match_destination_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['conditions.0.type']);
    }

    public function test_returns_policies_ordered_by_priority(): void
    {
        CallRoutingPolicy::factory()->create(['tenant_id' => $this->tenant->id, 'priority' => 30]);
        CallRoutingPolicy::factory()->create(['tenant_id' => $this->tenant->id, 'priority' => 10]);
        CallRoutingPolicy::factory()->create(['tenant_id' => $this->tenant->id, 'priority' => 20]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-routing-policies");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(10, $data[0]['priority']);
        $this->assertEquals(20, $data[1]['priority']);
        $this->assertEquals(30, $data[2]['priority']);
    }

    public function test_cannot_access_another_tenants_policy(): void
    {
        $otherTenant = Tenant::factory()->create();
        $policy = CallRoutingPolicy::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-routing-policies/{$policy->id}");

        $response->assertStatus(404);
    }
}
