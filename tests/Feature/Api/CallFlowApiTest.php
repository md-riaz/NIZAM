<?php

namespace Tests\Feature\Api;

use App\Models\CallFlow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallFlowApiTest extends TestCase
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

    public function test_can_list_call_flows_for_a_tenant(): void
    {
        CallFlow::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-flows");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_call_flow(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/call-flows", [
                'name' => 'Welcome Flow',
                'description' => 'Main greeting flow',
                'nodes' => [
                    [
                        'id' => 'start',
                        'type' => 'play_prompt',
                        'data' => ['file' => 'welcome.wav'],
                        'next' => 'bridge1',
                    ],
                    [
                        'id' => 'bridge1',
                        'type' => 'bridge',
                        'data' => ['destination_type' => 'extension', 'destination_id' => fake()->uuid()],
                        'next' => null,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('call_flows', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Welcome Flow',
        ]);
    }

    public function test_can_show_a_call_flow(): void
    {
        $flow = CallFlow::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-flows/{$flow->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $flow->name]);
    }

    public function test_can_update_a_call_flow(): void
    {
        $flow = CallFlow::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tenants/{$this->tenant->id}/call-flows/{$flow->id}", [
                'name' => 'Updated Flow',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('call_flows', [
            'id' => $flow->id,
            'name' => 'Updated Flow',
        ]);
    }

    public function test_can_delete_a_call_flow(): void
    {
        $flow = CallFlow::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/call-flows/{$flow->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('call_flows', ['id' => $flow->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/call-flows", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'nodes']);
    }

    public function test_validates_node_types(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/call-flows", [
                'name' => 'Test',
                'nodes' => [
                    ['id' => 'start', 'type' => 'invalid_type', 'data' => [], 'next' => null],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nodes.0.type']);
    }

    public function test_cannot_access_another_tenants_flow(): void
    {
        $otherTenant = Tenant::factory()->create();
        $flow = CallFlow::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/call-flows/{$flow->id}");

        $response->assertStatus(404);
    }
}
