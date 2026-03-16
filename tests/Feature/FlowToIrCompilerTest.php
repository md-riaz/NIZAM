<?php

namespace Tests\Feature;

use App\Models\Flow;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\Tenant;
use App\Services\Flow\Compile\FlowToIrCompiler;
use App\Services\Flow\Compile\NodeSpecRegistry;
use Tests\TestCase;

class FlowToIrCompilerTest extends TestCase
{
    protected FlowToIrCompiler $compiler;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $registry = new NodeSpecRegistry();
        $this->compiler = new FlowToIrCompiler($registry);
    }

    public function test_can_compile_start_node(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $startNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'start',
            'config' => [],
        ]);

        $nextNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'schedule_check',
            'config' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $nextNode->id,
            'transition_result' => 'next',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertEquals('AnswerAndTransfer', $instructions[0]->type);
    }

    public function test_can_compile_schedule_check_node(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $scheduleCheckNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'schedule_check',
            'config' => ['schedule_id' => 1],
        ]);

        $nextNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'menu',
            'config' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleCheckNode->id,
            'target_node_id' => $nextNode->id,
            'transition_result' => 'open',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertEquals('CheckSchedule', $instructions[0]->type);
        $this->assertEquals(1, $instructions[0]->config['schedule_id']);
    }

    public function test_can_compile_menu_node(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $menuNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'menu',
            'config' => ['prompt' => 'main-menu'],
        ]);

        $nextNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'voicemail',
            'config' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $menuNode->id,
            'target_node_id' => $nextNode->id,
            'transition_result' => 'digit_1',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertEquals('CollectDigits', $instructions[0]->type);
    }

    public function test_can_compile_voicemail_node(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $voicemailNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'voicemail',
            'config' => ['extension' => '100'],
        ]);

        $nextNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'hangup',
            'config' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $voicemailNode->id,
            'target_node_id' => $nextNode->id,
            'transition_result' => 'completed',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertEquals('Voicemail', $instructions[0]->type);
    }

    public function test_can_compile_hangup_node(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'hangup',
            'config' => [],
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertEquals('Hangup', $instructions[0]->type);
    }

    public function test_throws_on_unknown_node_type(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'unknown_type',
            'config' => [],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->compiler->compile($flowVersion);
    }

    public function test_can_validate_flow_before_compilation(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'start',
            'config' => [],
        ]);

        $this->assertTrue($this->compiler->canCompile($flowVersion));
    }

    public function test_cannot_validate_flow_with_unknown_node_type(): void
    {
        $flow = Flow::factory()->create(['tenant_id' => $this->tenant->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'node_type' => 'unknown_type',
            'config' => [],
        ]);

        $this->assertFalse($this->compiler->canCompile($flowVersion));
    }
}
