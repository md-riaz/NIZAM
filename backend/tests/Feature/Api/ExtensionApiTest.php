<?php

namespace Tests\Feature\Api;

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

class ExtensionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
        ]);
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_can_list_extensions_for_a_organization(): void
    {
        $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions");

        $response->assertStatus(200);
        $response->assertJsonFragment(['extension' => '101']);
    }

    public function test_can_create_an_extension_for_a_organization(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/extensions", [
                'extension' => '102',
                'password' => 'secret1234',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'follow_me_enabled' => true,
                'follow_me_destination' => '+15551234567',
                'voicemail_enabled' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.follow_me_enabled', true)
            ->assertJsonPath('data.follow_me_destination', '+15551234567');

        $this->assertDatabaseHas('extensions', [
            'extension' => '102',
            'organization_id' => $this->organization->id,
            'follow_me_enabled' => true,
            'follow_me_destination' => '+15551234567',
        ]);
    }

    public function test_can_show_an_extension(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['extension' => '101']);
    }

    public function test_can_update_an_extension(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}", [
                'extension' => '101',
                'password' => 'updated1234',
                'first_name' => 'Johnny',
                'last_name' => 'Doe',
                'follow_me_enabled' => true,
                'follow_me_destination' => '+15557654321',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.follow_me_enabled', true)
            ->assertJsonPath('data.follow_me_destination', '+15557654321');

        $this->assertDatabaseHas('extensions', [
            'id' => $extension->id,
            'first_name' => 'Johnny',
            'follow_me_enabled' => true,
            'follow_me_destination' => '+15557654321',
        ]);
    }

    public function test_can_delete_an_extension(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('extensions', ['id' => $extension->id]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/extensions", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['extension', 'password', 'first_name', 'last_name']);
    }

    public function test_rejects_legacy_caller_id_number_fields_on_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/extensions", [
                'extension' => '102',
                'password' => 'secret1234',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'effective_caller_id_number' => '+15551234567',
                'outbound_caller_id_number' => '+15557654321',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['effective_caller_id_number', 'outbound_caller_id_number']);
    }

    public function test_rejects_legacy_caller_id_number_fields_on_update(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}", [
                'extension' => '101',
                'password' => 'updated1234',
                'first_name' => 'Johnny',
                'last_name' => 'Doe',
                'effective_caller_id_number' => '+15551234567',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['effective_caller_id_number']);
    }

    public function test_returns_validation_error_when_creating_forwarding_without_destination(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/extensions", [
                'extension' => '102',
                'password' => 'secret1234',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'follow_me_enabled' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['follow_me_destination']);
    }

    public function test_dnd_update_retains_stored_follow_me_destination(): void
    {
        $extension = $this->organization->extensions()->create([
            'extension' => '101',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'follow_me_enabled' => true,
            'follow_me_destination' => '+15551234567',
            'dnd_enabled' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}", [
                'extension' => '101',
                'password' => 'secret1234',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'dnd_enabled' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.follow_me_enabled', false)
            ->assertJsonPath('data.follow_me_destination', '+15551234567')
            ->assertJsonPath('data.dnd_enabled', true);

        $this->assertDatabaseHas('extensions', [
            'id' => $extension->id,
            'follow_me_enabled' => false,
            'follow_me_destination' => '+15551234567',
            'dnd_enabled' => true,
        ]);
    }

    public function test_direct_extension_model_update_refreshes_published_team_flow_artifact(): void
    {
        $extension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);
        $otherExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
            'password' => 'secret1234',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
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
            'endpoint_type' => 'extension',
            'endpoint_id' => $extension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $otherExtension->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Direct Extension Rename Flow',
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
        $this->assertStringNotContainsString('user/201@'.$this->organization->domain, $beforeManifest->content);

        $extension->update(['extension' => '201']);

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringContainsString('user/201@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_updating_extension_number_refreshes_published_team_flow_artifact(): void
    {
        $extension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);
        $otherExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
            'password' => 'secret1234',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
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
            'endpoint_type' => 'extension',
            'endpoint_id' => $extension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $otherExtension->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Extension Rename Flow',
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
        $this->assertStringNotContainsString('user/201@'.$this->organization->domain, $beforeManifest->content);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}", [
                'extension' => '201',
                'password' => 'secret1234',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.extension', '201');

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringContainsString('user/201@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_direct_extension_model_active_state_update_refreshes_published_team_flow_artifact(): void
    {
        $extension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);
        $otherExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
            'password' => 'secret1234',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
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
            'endpoint_type' => 'extension',
            'endpoint_id' => $extension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $otherExtension->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Direct Extension Active Flow',
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

        $extension->update(['is_active' => false]);

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringContainsString('user/1002@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_updating_extension_active_state_refreshes_published_team_flow_artifact(): void
    {
        $extension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);
        $otherExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
            'password' => 'secret1234',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
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
            'endpoint_type' => 'extension',
            'endpoint_id' => $extension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $otherExtension->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Extension Active Flow',
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
            ->putJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}", [
                'extension' => '1001',
                'password' => 'secret1234',
                'first_name' => 'John',
                'last_name' => 'Doe',
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
        $this->assertStringContainsString('user/1002@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_deleting_extension_refreshes_published_team_flow_artifact(): void
    {
        $extension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);
        $otherExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
            'password' => 'secret1234',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
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
            'endpoint_type' => 'extension',
            'endpoint_id' => $extension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $otherExtension->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Extension Delete Flow',
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
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/extensions/{$extension->id}");

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
