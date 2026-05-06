<?php

namespace Tests\Feature\Api;

use App\Models\Extension;
use App\Models\Flow;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\HolidayCalendar;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Flow\FlowPublishService;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamApiTest extends TestCase
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

    public function test_can_show_team_with_inbound_routing_fields(): void
    {
        $holidayCalendar = HolidayCalendar::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $schedule = Schedule::factory()->create([
            'organization_id' => $this->organization->id,
            'holiday_calendar_id' => $holidayCalendar->id,
        ]);
        $team = Team::create([
            'organization_id' => $this->organization->id,
            'schedule_id' => $schedule->id,
            'holiday_calendar_id' => $holidayCalendar->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/teams/{$team->id}");

        $response->assertOk()
            ->assertJsonPath('data.schedule_id', $schedule->id)
            ->assertJsonPath('data.holiday_calendar_id', $holidayCalendar->id)
            ->assertJsonPath('data.name', 'Sales');
    }

    public function test_can_create_team_with_inbound_routing_fields(): void
    {
        $holidayCalendar = HolidayCalendar::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $schedule = Schedule::factory()->create([
            'organization_id' => $this->organization->id,
            'holiday_calendar_id' => $holidayCalendar->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/teams", [
                'schedule_id' => $schedule->id,
                'holiday_calendar_id' => $holidayCalendar->id,
                'name' => 'Support',
                'strategy' => 'priority',
                'timeout' => 20,
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.schedule_id', $schedule->id)
            ->assertJsonPath('data.holiday_calendar_id', $holidayCalendar->id);

        $this->assertDatabaseHas('teams', [
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'schedule_id' => $schedule->id,
            'holiday_calendar_id' => $holidayCalendar->id,
        ]);
    }

    public function test_rejects_team_create_with_schedule_from_other_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $foreignSchedule = Schedule::factory()->create([
            'organization_id' => $otherOrganization->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/teams", [
                'schedule_id' => $foreignSchedule->id,
                'name' => 'Support',
                'strategy' => 'priority',
                'timeout' => 20,
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_id']);
    }

    public function test_replacing_team_members_rebuilds_manifest_and_refreshes_published_team_flow_artifact(): void
    {
        $firstExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);
        $secondExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
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
            'endpoint_id' => $firstExtension->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Team Route Flow',
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
        $this->assertStringNotContainsString('user/1002@'.$this->organization->domain, $beforeManifest->content);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/teams/{$team->id}", [
                'name' => 'Sales',
                'strategy' => 'simultaneous',
                'timeout' => 30,
                'is_active' => true,
                'members' => [
                    [
                        'endpoint_type' => 'extension',
                        'endpoint_id' => $secondExtension->id,
                        'priority' => 1,
                        'is_active' => true,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.members');

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringContainsString('user/1002@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_updating_team_strategy_refreshes_published_team_flow_artifact(): void
    {
        $firstExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);
        $secondExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
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
            'endpoint_id' => $firstExtension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $secondExtension->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Team Strategy Flow',
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
        $this->assertStringContainsString('user/1001@'.$this->organization->domain.',user/1002@'.$this->organization->domain, $beforeManifest->content);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/organizations/{$this->organization->id}/teams/{$team->id}", [
                'name' => 'Sales',
                'strategy' => 'priority',
                'timeout' => 30,
                'is_active' => true,
            ]);

        $response->assertOk();

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringContainsString('user/1001@'.$this->organization->domain.'|user/1002@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain.',user/1002@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_direct_team_model_update_refreshes_published_team_flow_artifact(): void
    {
        $firstExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);
        $secondExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
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
            'endpoint_id' => $firstExtension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $secondExtension->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Direct Team Update Flow',
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
        $this->assertStringContainsString('user/1001@'.$this->organization->domain.',user/1002@'.$this->organization->domain, $beforeManifest->content);

        $team->update(['strategy' => 'priority']);

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringContainsString('user/1001@'.$this->organization->domain.'|user/1002@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain.',user/1002@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_direct_team_member_model_update_refreshes_published_team_flow_artifact(): void
    {
        $firstExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);
        $secondExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
            'is_active' => true,
        ]);

        $team = Team::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 30,
            'is_active' => true,
        ]);

        $teamMember = TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $firstExtension->id,
            'priority' => 1,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Direct Team Member Update Flow',
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
        $this->assertStringNotContainsString('user/1002@'.$this->organization->domain, $beforeManifest->content);

        $teamMember->update(['endpoint_id' => $secondExtension->id]);

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringContainsString('user/1002@'.$this->organization->domain, $afterManifest->content);
    }

    public function test_deleting_team_refreshes_published_team_flow_artifact(): void
    {
        $firstExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);
        $secondExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
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
            'endpoint_id' => $firstExtension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $secondExtension->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Team Delete Flow',
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
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/teams/{$team->id}");

        $response->assertNoContent();

        $afterManifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($afterManifest);
        $this->assertStringNotContainsString('user/1001@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringNotContainsString('user/1002@'.$this->organization->domain, $afterManifest->content);
        $this->assertStringContainsString('BridgeTeam node_', $afterManifest->content);
        $this->assertStringContainsString('resolved to empty dial string', $afterManifest->content);
    }

    public function test_deleting_team_member_refreshes_published_team_flow_artifact(): void
    {
        $firstExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);
        $secondExtension = Extension::factory()->create([
            'organization_id' => $this->organization->id,
            'extension' => '1002',
            'is_active' => true,
        ]);

        $team = Team::create([
            'organization_id' => $this->organization->id,
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'timeout' => 30,
            'is_active' => true,
        ]);

        $teamMember = TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $firstExtension->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'endpoint_type' => 'extension',
            'endpoint_id' => $secondExtension->id,
            'priority' => 2,
            'is_active' => true,
        ]);

        $flow = Flow::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Team Member Delete Flow',
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

        $teamMember->delete();

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
