<?php

namespace Tests\Feature\Api;

use App\Models\Flow;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlowApiTest extends TestCase
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

    public function test_can_list_call_flows_for_a_organization(): void
    {
        Flow::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/flows");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_a_call_flow(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows", [
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
            'organization_id' => $this->organization->id,
            'name' => 'Welcome Flow',
        ]);
    }

    public function test_can_show_a_call_flow(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/flows/{$flow->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $flow->name]);
    }

    public function test_can_update_a_call_flow(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/flows/{$flow->id}", [
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
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/flows/{$flow->id}", [
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
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows", [
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
            ->getJson("/api/v1/organizations/{$this->organization->id}/flows/{$flow['id']}");

        $response->assertStatus(200)
            ->assertJsonPath('data.latest_version.nodes.0.name', 'Call Start Reload Check')
            ->assertJsonPath('data.latest_version.nodes.0.type', 'start');
    }

    public function test_can_delete_a_call_flow(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/flows/{$flow->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('flows', ['id' => $flow->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'version.definition.nodes']);
    }

    public function test_validates_node_types(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows", [
                'name' => 'Test',
                'nodes' => [
                    ['id' => 'start', 'type' => 'invalid_type', 'data' => [], 'next' => null],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version.definition.nodes']);
    }

    public function test_cannot_access_another_organizations_flow(): void
    {
        $otherOrganization = Organization::factory()->create();
        $flow = Flow::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/flows/{$flow->id}");

        $response->assertStatus(404);
    }

    public function test_can_create_flow_with_business_hours_and_end_call_nodes(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows", [
                'name' => 'After Hours Flow',
                'description' => 'Routes by business state',
                'version' => [
                    'definition' => [
                        'nodes' => [
                            [
                                'id' => 'start',
                                'type' => 'start',
                                'config' => [],
                            ],
                            [
                                'id' => 'hours',
                                'type' => 'business_hours',
                                'config' => [
                                    'schedule_mode' => 'organization_default',
                                ],
                            ],
                            [
                                'id' => 'closed',
                                'type' => 'end_call',
                                'config' => [
                                    'hangup_cause' => 'NORMAL_CLEARING',
                                ],
                            ],
                        ],
                        'edges' => [
                            [
                                'id' => 'e1',
                                'source_node_id' => 'start',
                                'target_node_id' => 'hours',
                                'condition' => 'next',
                            ],
                            [
                                'id' => 'e2',
                                'source_node_id' => 'hours',
                                'target_node_id' => 'closed',
                                'condition' => 'closed',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertCreated();
    }

    public function test_existing_terminal_node_type_still_round_trips(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows", [
                'name' => 'Legacy Flow',
                'version' => [
                    'definition' => [
                        'nodes' => [
                            [
                                'id' => 'start',
                                'type' => 'start',
                                'config' => [],
                            ],
                            [
                                'id' => 'end',
                                'type' => 'terminal',
                                'config' => [],
                            ],
                        ],
                        'edges' => [
                            [
                                'id' => 'e1',
                                'source_node_id' => 'start',
                                'target_node_id' => 'end',
                                'condition' => 'next',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertCreated();
    }

    public function test_publish_returns_validation_error_for_unreachable_flow(): void
    {
        $flow = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows", [
                'name' => 'Invalid Publish Flow',
                'version' => [
                    'definition' => [
                        'nodes' => [
                            [
                                'id' => 'start',
                                'type' => 'start',
                                'config' => [],
                            ],
                            [
                                'id' => 'play-message',
                                'type' => 'play_message',
                                'config' => [
                                    'prompt' => 'recordings/1/welcome.wav',
                                ],
                            ],
                        ],
                        'edges' => [],
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows/{$flow['id']}/publish");

        $response->assertStatus(422);
        $this->assertStringContainsString('[play_message] is unreachable.', (string) $response->json('message'));
    }

    public function test_can_publish_saved_play_message_flow(): void
    {
        Storage::fake('public');

        $media = $this->organization
            ->addMedia(UploadedFile::fake()->createWithContent('welcome.wav', "RIFF\x24\x00\x00\x00WAVEfmt "))
            ->usingName('Welcome Greeting')
            ->toMediaCollection('prompts');

        $flow = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows", [
                'name' => 'Publish Play Message Flow',
                'version' => [
                    'definition' => [
                        'nodes' => [
                            [
                                'id' => 'start',
                                'type' => 'start',
                                'config' => [],
                            ],
                            [
                                'id' => 'play-message',
                                'type' => 'play_message',
                                'config' => [
                                    'media_id' => (string) $media->id,
                                ],
                            ],
                            [
                                'id' => 'end',
                                'type' => 'end_call',
                                'config' => [
                                    'hangup_cause' => 'NORMAL_CLEARING',
                                ],
                            ],
                        ],
                        'edges' => [
                            [
                                'id' => 'edge-1',
                                'source_node_id' => 'start',
                                'target_node_id' => 'play-message',
                                'condition' => 'next',
                            ],
                            [
                                'id' => 'edge-2',
                                'source_node_id' => 'play-message',
                                'target_node_id' => 'end',
                                'condition' => 'next',
                            ],
                        ],
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows/{$flow['id']}/publish");

        $response->assertOk()
            ->assertJsonPath('data.active_version.status', 'published')
            ->assertJsonPath('data.active_version.is_published', true);
    }

    public function test_flow_create_hydrates_menu_prompt_from_media_id(): void
    {
        Storage::fake('public');

        $media = $this->organization
            ->addMedia(UploadedFile::fake()->createWithContent('welcome.wav', "RIFF\x24\x00\x00\x00WAVEfmt "))
            ->usingName('Welcome Greeting')
            ->toMediaCollection('prompts');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/flows", [
                'name' => 'Media Prompt Flow',
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
                                    'media_id' => (string) $media->id,
                                    'digits' => ['1' => 'next'],
                                    'timeout' => 30,
                                ],
                            ],
                        ],
                        'edges' => [
                            [
                                'id' => 'edge-1',
                                'source_node_id' => 'start',
                                'target_node_id' => 'menu',
                                'condition' => 'next',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertCreated();

        $menuNode = collect($response->json('data.latest_version.nodes'))
            ->firstWhere('type', 'menu');

        $this->assertNotNull($menuNode);
        $this->assertSame((string) $media->id, data_get($menuNode, 'config.media_id'));
        $this->assertSame((string) $media->id, data_get($menuNode, 'config.prompt_media_id'));
        $this->assertSame('recordings/'.$media->id.'/'.$media->file_name, data_get($menuNode, 'config.prompt'));
    }
}
