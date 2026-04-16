<?php

namespace Tests\Feature\Api;

use App\Models\Flow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowApiTest extends TestCase
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
        Flow::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/flows");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_call_flow(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/flows", [
                'name' => 'Welcome Flow',
                'description' => 'Main greeting flow',
                'version' => [
                    'definition' => [
                        'nodes' => [
                            [
                                'id' => 'start',
                                'type' => 'start',
                                'config' => [],
                            ],
                            [
                                'id' => 'menu',
                                'type' => 'menu',
                                'config' => [
                                    'prompt' => 'welcome.wav',
                                    'digits' => ['1' => 'next'],
                                    'timeout' => 30,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('flows', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Welcome Flow',
        ]);
    }

    public function test_can_show_a_call_flow(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/flows/{$flow->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $flow->name]);
    }

    public function test_can_update_a_call_flow(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tenants/{$this->tenant->id}/flows/{$flow->id}", [
                'name' => 'Updated Flow',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('flows', [
            'id' => $flow->id,
            'name' => 'Updated Flow',
        ]);
    }

    public function test_update_response_includes_latest_version_definition_for_draft_rehydration(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/tenants/{$this->tenant->id}/flows/{$flow->id}", [
                'name' => 'Updated Flow',
                'version' => [
                    'definition' => [
                        'nodes' => [
                            [
                                'id' => 'start-node',
                                'type' => 'start',
                                'name' => 'Call Start Reload Check',
                                'config' => [],
                            ],
                        ],
                        'edges' => [],
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.latest_version.nodes.0.name', 'Call Start Reload Check')
            ->assertJsonPath('data.latest_version.nodes.0.type', 'start');
    }

    public function test_show_response_includes_latest_version_definition_for_draft_rehydration(): void
    {
        $flow = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/flows", [
                'name' => 'Smoke Test Flow',
                'description' => 'Draft rehydration test',
                'version' => [
                    'definition' => [
                        'nodes' => [
                            [
                                'id' => 'start-node',
                                'type' => 'start',
                                'name' => 'Call Start Reload Check',
                                'config' => [],
                            ],
                        ],
                        'edges' => [],
                    ],
                ],
            ])
            ->assertStatus(201)
            ->json('data');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/flows/{$flow['id']}");

        $response->assertStatus(200)
            ->assertJsonPath('data.latest_version.nodes.0.name', 'Call Start Reload Check')
            ->assertJsonPath('data.latest_version.nodes.0.type', 'start');
    }

    public function test_can_delete_a_call_flow(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/flows/{$flow->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('flows', ['id' => $flow->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/flows", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'version.definition.nodes']);
    }

    public function test_validates_node_types(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/flows", [
                'name' => 'Test',
                'nodes' => [
                    ['id' => 'start', 'type' => 'invalid_type', 'data' => [], 'next' => null],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version.definition.nodes']);
    }

    public function test_cannot_access_another_tenants_flow(): void
    {
        $otherTenant = Tenant::factory()->create();
        $flow = Flow::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/flows/{$flow->id}");

        $response->assertStatus(404);
    }
}
