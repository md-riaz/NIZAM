<?php

namespace Tests\Feature;

use App\Models\Flow;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Services\Flow\FlowArtifactService;
use App\Services\Flow\FlowPublishService;
use App\Services\Flow\Compile\FlowToIrCompiler;
use App\Domain\Flow\Compile\NodeSpecRegistry;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowPublishServiceTest extends TestCase
{
    use RefreshDatabase;
    protected FlowPublishService $publishService;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['domain' => 'test.example.com']);
        $registry = new NodeSpecRegistry();
        $compiler = new FlowToIrCompiler($registry);
        $artifactService = new FlowArtifactService($compiler, app(\App\Services\Routing\RoutingGraphCompiler::class));
        $manifestBuilder = new OrganizationManifestBuilder(app(\App\Services\DialplanCompiler::class));
        $integrityValidator = app(\App\Services\Flow\FlowIntegrityValidator::class);
        $this->publishService = new FlowPublishService($artifactService, $manifestBuilder, $integrityValidator);
    }

    public function test_can_publish_flow_version(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
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

        $scheduleCheckNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'schedule_check',
            'config_json' => ['schedule_id' => 1, 'schedule_mode' => 'custom'],
        ]);
        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $scheduleCheckNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleCheckNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'open',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleCheckNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'closed',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleCheckNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'holiday',
        ]);

        $result = $this->publishService->publish($flowVersion);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('artifact_id', $result);
        $this->assertArrayHasKey('checksum', $result);

        // Verify artifact was created
        $artifact = \App\Models\FlowCompiledArtifact::where('flow_version_id', $flowVersion->id)->first();
        $this->assertNotNull($artifact);

        // Verify manifest was created and activated
        $manifest = OrganizationDialplanManifest::where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();
        $this->assertNotNull($manifest);

        // Verify flow version status was updated
        $flowVersion->refresh();
        $this->assertEquals('published', $flowVersion->status);
        $this->assertTrue($flowVersion->is_published);
    }

    public function test_can_publish_play_message_flow_version(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
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

        $playMessageNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'play_message',
            'config_json' => [
                'prompt' => 'recordings/1/welcome.wav',
                'destination_type' => 'extension',
                'destination_value' => '1003',
            ],
        ]);

        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $playMessageNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $playMessageNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'next',
        ]);

        $result = $this->publishService->publish($flowVersion);

        $this->assertTrue($result['success']);

        $artifact = \App\Models\FlowCompiledArtifact::where('flow_version_id', $flowVersion->id)->first();
        $this->assertNotNull($artifact);
        $this->assertStringContainsString('application="set" data="nizam_destination_type=extension"', $artifact->content);
        $this->assertStringContainsString('application="set" data="nizam_destination_id=1003"', $artifact->content);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML '.$this->organization->domain, $artifact->content);
        $this->assertStringNotContainsString('application="playback"', $artifact->content);
    }

    public function test_publish_fails_for_invalid_flow(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'status' => 'draft',
            'definition_json' => [],
        ]);

        // Add an unknown node type
        FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'unknown_type',
            'config_json' => [],
        ]);

        $result = $this->publishService->publish($flowVersion);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('exactly one start node', $result['error']);
        $this->assertStringContainsString('unreachable', $result['error']);

        // Verify no artifact was created
        $artifact = \App\Models\FlowCompiledArtifact::where('flow_version_id', $flowVersion->id)->first();
        $this->assertNull($artifact);
    }

    public function test_rollback_flow_version(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'status' => 'published',
            'definition_json' => [],
        ]);

        $startNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
            'config_json' => [],
        ]);

        $scheduleCheckNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'schedule_check',
            'config_json' => ['schedule_id' => 1, 'schedule_mode' => 'custom'],
        ]);
        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $scheduleCheckNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleCheckNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'open',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleCheckNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'closed',
        ]);

        // Publish first
        $this->publishService->publish($flowVersion);

        // Rollback
        $result = $this->publishService->rollback($flowVersion);

        $this->assertTrue($result);

        // Verify flow version status was updated
        $flowVersion->refresh();
        $this->assertEquals('draft', $flowVersion->status);

        // Verify manifest was updated (not necessarily null, but rebuilt)
        $manifest = OrganizationDialplanManifest::where('organization_id', $this->organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();
        $this->assertNotNull($manifest);
    }

    public function test_publish_creates_unique_artifact_per_flow_version(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion1 = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'version_number' => 1,
            'status' => 'draft',
            'definition_json' => [],
        ]);

        $startNode1 = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion1->id,
            'type' => 'start',
            'config_json' => [],
        ]);

        $menuNode1 = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion1->id,
            'type' => 'menu',
            'config_json' => ['prompt' => 'version-1'],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion1->id,
            'source_node_id' => $startNode1->id,
            'target_node_id' => $menuNode1->id,
            'condition' => 'next',
        ]);

        $result1 = $this->publishService->publish($flowVersion1);
        $this->assertTrue($result1['success']);

        $flowVersion2 = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'version_number' => 2,
            'status' => 'draft',
            'definition_json' => [],
        ]);

        $startNode2 = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion2->id,
            'type' => 'start',
            'config_json' => [],
        ]);

        $menuNode2 = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion2->id,
            'type' => 'menu',
            'config_json' => ['prompt' => 'version-2'],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion2->id,
            'source_node_id' => $startNode2->id,
            'target_node_id' => $menuNode2->id,
            'condition' => 'next',
        ]);

        $result2 = $this->publishService->publish($flowVersion2);
        $this->assertTrue($result2['success']);

        $artifact1 = \App\Models\FlowCompiledArtifact::where('flow_version_id', $flowVersion1->id)->first();
        $artifact2 = \App\Models\FlowCompiledArtifact::where('flow_version_id', $flowVersion2->id)->first();

        $this->assertNotNull($artifact1);
        $this->assertNotNull($artifact2);
        $this->assertNotEquals($artifact1->checksum, $artifact2->checksum);
    }

    public function test_publish_fails_when_business_hours_missing_holiday_branch(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
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

        $hoursNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'business_hours',
            'config_json' => ['schedule_mode' => 'organization_default'],
        ]);

        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'end_call',
            'config_json' => ['hangup_cause' => 'NORMAL_CLEARING'],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $hoursNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $hoursNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'open',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $hoursNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'closed',
        ]);

        $result = $this->publishService->publish($flowVersion);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('holiday', $result['error']);
    }
}
