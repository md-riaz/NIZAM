<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Flow\FlowPublishService;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private Extension $extension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Test Corp',
            'domain' => 'test.example.com',
            'max_extensions' => 50,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->extension = $this->organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }

    public function test_can_list_agents(): void
    {
        Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/agents");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_create_agent(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/agents", [
                'extension_id' => $this->extension->id,
                'name' => 'Agent Smith',
                'role' => 'agent',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Agent Smith', 'role' => 'agent']);

        $this->assertDatabaseHas('agents', [
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);
    }

    public function test_cannot_create_duplicate_agent_for_extension(): void
    {
        Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/agents", [
                'extension_id' => $this->extension->id,
                'name' => 'Agent Jones',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('extension_id');
    }

    public function test_can_show_agent(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/agents/{$agent->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Agent Smith']);
    }

    public function test_can_update_agent(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/agents/{$agent->id}", [
                'name' => 'Agent Jones',
                'role' => 'supervisor',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Agent Jones', 'role' => 'supervisor']);
    }

    public function test_can_delete_agent(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/agents/{$agent->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
    }

    public function test_can_change_agent_state(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_OFFLINE,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/agents/{$agent->id}/state", [
                'state' => 'available',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['state' => 'available']);

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'state' => 'available',
        ]);
    }

    public function test_can_pause_agent_with_reason(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_AVAILABLE,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/agents/{$agent->id}/state", [
                'state' => 'paused',
                'pause_reason' => 'lunch',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['state' => 'paused', 'pause_reason' => 'lunch']);
    }

    public function test_pause_requires_reason(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
            'state' => Agent::STATE_AVAILABLE,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/agents/{$agent->id}/state", [
                'state' => 'paused',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('pause_reason');
    }

    public function test_invalid_state_rejected(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/agents/{$agent->id}/state", [
                'state' => 'invalid_state',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('state');
    }

    public function test_organization_isolation_for_agents(): void
    {
        $otherOrganization = Organization::create([
            'name' => 'Other Corp',
            'domain' => 'other.example.com',
            'max_extensions' => 50,
        ]);

        $otherExt = $otherOrganization->extensions()->create([
            'extension' => '2001',
            'password' => 'secret123',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $otherAgent = Agent::create([
            'organization_id' => $otherOrganization->id,
            'extension_id' => $otherExt->id,
            'name' => 'Other Agent',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/agents/{$otherAgent->id}");

        $response->assertStatus(404);
    }

    public function test_direct_agent_model_update_refreshes_published_team_flow_artifact(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
            'role' => Agent::ROLE_AGENT,
            'is_active' => true,
        ]);

        $team = Team::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 30,
            'is_active' => true,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'agent',
            'endpoint_id' => $agent->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Direct Agent Route Flow',
        ]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'status' => 'draft',
            'definition_json' => [],
        ]);

        $startNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
            'config_json' => [],
        ]);
        $teamNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'ring_team',
            'config_json' => [
                'team_id' => $team->id,
                'timeout' => 30,
            ],
        ]);
        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => [
                'hangup_cause' => 'NORMAL_CLEARING',
            ],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $teamNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $teamNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'no_answer',
        ]);

        app(FlowPublishService::class)->publish($flowVersion);
        app(OrganizationManifestBuilder::class)->buildAndActivate($this->organization->fresh());

        $beforeManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($beforeManifest);
        $this->assertStringContainsString('user/1001@'.$this->organization->domain, $beforeManifest->content);

        $agent->update(['is_active' => false]);

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_updating_agent_active_state_refreshes_published_team_flow_artifact(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
            'role' => Agent::ROLE_AGENT,
            'is_active' => true,
        ]);

        $team = Team::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 30,
            'is_active' => true,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'agent',
            'endpoint_id' => $agent->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Agent Route Flow',
        ]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'status' => 'draft',
            'definition_json' => [],
        ]);

        $startNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
            'config_json' => [],
        ]);
        $teamNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'ring_team',
            'config_json' => [
                'team_id' => $team->id,
                'timeout' => 30,
            ],
        ]);
        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => [
                'hangup_cause' => 'NORMAL_CLEARING',
            ],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $teamNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $teamNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'no_answer',
        ]);

        app(FlowPublishService::class)->publish($flowVersion);
        app(OrganizationManifestBuilder::class)->buildAndActivate($this->organization->fresh());

        $beforeManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($beforeManifest);
        $this->assertStringContainsString('user/1001@'.$this->organization->domain, $beforeManifest->content);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/agents/{$agent->id}", [
                'name' => 'Agent Smith',
                'role' => Agent::ROLE_AGENT,
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_deleting_agent_refreshes_published_team_flow_artifact(): void
    {
        $agent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $this->extension->id,
            'name' => 'Agent Smith',
            'role' => Agent::ROLE_AGENT,
            'is_active' => true,
        ]);

        $otherExtension = $this->organization->extensions()->create([
            'extension' => '1002',
            'password' => 'secret1234',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);
        $otherAgent = Agent::create([
            'organization_id' => $this->organization->id,
            'extension_id' => $otherExtension->id,
            'name' => 'Agent Jones',
            'role' => Agent::ROLE_AGENT,
            'is_active' => true,
        ]);

        $team = Team::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 30,
            'is_active' => true,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'agent',
            'endpoint_id' => $agent->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'agent',
            'endpoint_id' => $otherAgent->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Agent Delete Flow',
        ]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'status' => 'draft',
            'definition_json' => [],
        ]);

        $startNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
            'config_json' => [],
        ]);
        $teamNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'ring_team',
            'config_json' => [
                'team_id' => $team->id,
                'timeout' => 30,
            ],
        ]);
        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => [
                'hangup_cause' => 'NORMAL_CLEARING',
            ],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $teamNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $teamNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'no_answer',
        ]);

        app(FlowPublishService::class)->publish($flowVersion);
        app(OrganizationManifestBuilder::class)->buildAndActivate($this->organization->fresh());

        $beforeManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($beforeManifest);
        $this->assertStringContainsString('user/1001@'.$this->organization->domain, $beforeManifest->content);
        $this->assertStringContainsString('user/1002@'.$this->organization->domain, $beforeManifest->content);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/agents/{$agent->id}");

        $response->assertNoContent();

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringContainsString('user/1002@'.$this->organization->domain, $afterManifest->content);
    }
}
