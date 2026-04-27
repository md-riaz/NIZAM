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
            'config_json' => [
                'greeting' => 'main-menu',
                'short_greeting' => 'press 1',
                'destination_type' => 'extension',
                'destination_value' => '1001',
                'timeout' => 5,
                'max_failures' => 3,
                'options' => [
                    ['digit' => '1', 'label' => 'Sales'],
                ],
            ],
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
            'condition' => 'digit_1',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertNotEmpty($instructions);
        $this->assertEquals('CollectDigits', $instructions[0]->type);
        $this->assertSame('main-menu', $instructions[0]->params['config']['prompt']);
        $this->assertSame(['1'], $instructions[0]->params['config']['digits']);
        $this->assertSame('extension', $instructions[0]->params['destination_type']);
        $this->assertSame('1001', $instructions[0]->params['destination_value']);
        $this->assertSame("node_{$nextNode->id}", $instructions[0]->transitions['digit_1']);
    }

    public function test_can_compile_play_message_node(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $playMessageNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'play_message',
            'config_json' => [
                'prompt' => 'recordings/1/welcome.wav',
                'media_id' => '1',
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
            'source_node_id' => $playMessageNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'next',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $instruction = collect($instructions)->first(fn ($item) => $item->type === 'PlayMessage');

        $this->assertNotNull($instruction);
        $this->assertSame('recordings/1/welcome.wav', $instruction->params['config']['prompt']);
        $this->assertSame('1', $instruction->params['config']['media_id']);
        $this->assertSame("node_{$hangupNode->id}", $instruction->transitions['next']);
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

    public function test_can_compile_caller_match_node(): void
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

        $callerMatchNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'caller_match',
            'config_json' => [
                'mode' => 'exact',
                'numbers' => ['+15551234567'],
            ],
        ]);

        $ringTeamNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'ring_team',
            'config_json' => [
                'team_id' => 'vip-team',
                'timeout' => 25,
                'strategy' => 'simultaneous',
                'members_text' => "1001,20,0\n1002,20,5",
            ],
        ]);

        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => ['hangup_cause' => 'NORMAL_CLEARING'],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $callerMatchNode->id,
            'condition' => 'next',
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $callerMatchNode->id,
            'target_node_id' => $ringTeamNode->id,
            'condition' => 'match',
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $callerMatchNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'no_match',
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $ringTeamNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'failed',
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $ringTeamNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'no_answer',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertContains('MatchCaller', array_map(fn ($instruction) => $instruction->type, $instructions));

        $ringInstruction = collect($instructions)->first(fn ($instruction) => $instruction->type === 'BridgeTeam');

        $this->assertNotNull($ringInstruction);
        $this->assertSame('vip-team', $ringInstruction->params['team_id']);
        $this->assertSame(25, $ringInstruction->params['timeout']);
        $this->assertSame('simultaneous', $ringInstruction->params['config']['strategy']);
        $this->assertArrayHasKey('failed', $ringInstruction->transitions);
        $this->assertArrayHasKey('no_answer', $ringInstruction->transitions);
    }

    public function test_can_compile_number_match_node(): void
    {
        $flow = Flow::factory()->create(['organization_id' => $this->organization->id]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'definition_json' => [],
        ]);

        $numberMatchNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'number_match',
            'config_json' => [
                'mode' => 'did',
                'numbers' => ['+15550001111'],
            ],
        ]);

        $hangupNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
            'config_json' => [],
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $numberMatchNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'match',
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $numberMatchNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'no_match',
        ]);

        $instructions = $this->compiler->compile($flowVersion);

        $this->assertContains('MatchNumber', array_map(fn ($instruction) => $instruction->type, $instructions));
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
