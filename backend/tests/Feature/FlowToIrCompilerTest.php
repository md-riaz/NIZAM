<?php

namespace Tests\Feature;

use App\Models\Flow;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\Organization;
use App\Domain\Flow\Compile\NodeSpecRegistry;
use App\Services\Flow\Compile\FlowToIrCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowToIrCompilerTest extends TestCase
{
    use RefreshDatabase;
    protected FlowToIrCompiler $compiler;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['domain' => 'test.example.com']);
        $registry = new NodeSpecRegistry();
        $this->compiler = new FlowToIrCompiler($registry);
    }

    public function test_can_compile_start_node(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $startNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
            'config_json' => [],
        ]);

        $nextNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'schedule_check',
            'config_json' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $nextNode->id,
            'condition' => 'default',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertContains('AnswerAndTransfer', array_column($instructions, 'type'));
    }

    public function test_can_compile_schedule_check_node(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $scheduleCheckNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'schedule_check',
            'config_json' => ['schedule_id' => 1],
        ]);

        $nextNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'menu',
            'config_json' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleCheckNode->id,
            'target_node_id' => $nextNode->id,
            'condition' => 'default',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertContains('CheckSchedule', array_column($instructions, 'type'));
    }

    public function test_can_compile_menu_node(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $menuNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'menu',
            'config_json' => ['prompt' => 'main-menu'],
        ]);

        $nextNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'voicemail',
            'config_json' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $menuNode->id,
            'target_node_id' => $nextNode->id,
            'condition' => 'default',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertEquals('CollectDigits', $instructions[0]->type);
    }

    public function test_can_compile_voicemail_node(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $voicemailNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'voicemail',
            'config_json' => ['extension' => '100'],
        ]);

        $nextNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $voicemailNode->id,
            'target_node_id' => $nextNode->id,
            'condition' => 'default',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertContains('Voicemail', array_column($instructions, 'type'));
    }

    public function test_can_compile_hangup_node(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => [],
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertEquals('Hangup', $instructions[0]->type);
    }

    public function test_throws_on_unknown_type(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'unknown_type',
            'config_json' => [],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->compiler->compile($flowVersion);
    }

    public function test_can_validate_flow_before_compilation(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
            'config_json' => [],
        ]);

        $this->assertTrue($this->compiler->canCompile($flowVersion));
    }

    public function test_cannot_validate_flow_with_unknown_type(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'unknown_type',
            'config_json' => [],
        ]);

        $this->assertFalse($this->compiler->canCompile($flowVersion));
    }
}
