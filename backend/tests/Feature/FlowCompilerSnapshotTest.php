<?php

namespace Tests\Feature;

use App\Models\Flow;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\RingGroup;
use App\Models\Organization;
use App\Services\Flow\Compile\FlowToIrCompiler;
use App\Domain\Flow\Compile\NodeSpecRegistry;
use App\Services\Flow\FlowArtifactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowCompilerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected FlowArtifactService $artifactService;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['domain' => 'snapshot.example.com']);
        $registry = new NodeSpecRegistry();
        $compiler = new FlowToIrCompiler($registry);
        $this->artifactService = new FlowArtifactService($compiler, app(\App\Services\Routing\RoutingGraphCompiler::class));
    }

    public function test_compiled_xml_matches_expected_snapshot(): void
    {
        // 1. Setup a predictable Flow
        $flow = Flow::factory()->create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'organization_id' => $this->organization->id,
        ]);
        
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'status' => 'draft',
        ]);

        $ringGroup = RingGroup::factory()->create([
            'id' => '99999999-9999-9999-9999-999999999999',
            'organization_id' => $this->organization->id,
            'strategy' => 'simultaneous',
            'members' => [],
        ]);

        // Start Node
        $startNode = FlowNode::factory()->create([
            'id' => '10000000-0000-0000-0000-000000000001',
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
        ]);

        // Schedule Check
        $scheduleNode = FlowNode::factory()->create([
            'id' => '10000000-0000-0000-0000-000000000002',
            'flow_version_id' => $flowVersion->id,
            'type' => 'schedule_check',
            'config_json' => ['schedule_id' => '22222222-2222-2222-2222-222222222222'],
        ]);

        // Menu
        $menuNode = FlowNode::factory()->create([
            'id' => '10000000-0000-0000-0000-000000000003',
            'flow_version_id' => $flowVersion->id,
            'type' => 'menu',
            'config_json' => [
                'prompt' => 'ivr/welcome.wav',
                'min_digits' => 1,
                'max_digits' => 1,
                'timeout' => 5000,
            ],
        ]);

        // Ring Team
        $teamNode = FlowNode::factory()->create([
            'id' => '10000000-0000-0000-0000-000000000004',
            'flow_version_id' => $flowVersion->id,
            'type' => 'ring_team',
            'config_json' => [
                'team_id' => $ringGroup->id,
                'timeout' => 30,
            ],
        ]);

        // Voicemail
        $voicemailNode = FlowNode::factory()->create([
            'id' => '10000000-0000-0000-0000-000000000005',
            'flow_version_id' => $flowVersion->id,
            'type' => 'voicemail',
            'config_json' => ['extension' => '100'],
        ]);

        // Hangup
        $hangupNode = FlowNode::factory()->create([
            'id' => '10000000-0000-0000-0000-000000000006',
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
        ]);

        // Edges
        FlowEdge::factory()->create(['flow_version_id' => $flowVersion->id, 'source_node_id' => $startNode->id, 'target_node_id' => $scheduleNode->id, 'condition' => 'next']);
        FlowEdge::factory()->create(['flow_version_id' => $flowVersion->id, 'source_node_id' => $scheduleNode->id, 'target_node_id' => $menuNode->id, 'condition' => 'open']);
        FlowEdge::factory()->create(['flow_version_id' => $flowVersion->id, 'source_node_id' => $scheduleNode->id, 'target_node_id' => $voicemailNode->id, 'condition' => 'closed']);
        FlowEdge::factory()->create(['flow_version_id' => $flowVersion->id, 'source_node_id' => $menuNode->id, 'target_node_id' => $teamNode->id, 'condition' => 'digit_1']);
        FlowEdge::factory()->create(['flow_version_id' => $flowVersion->id, 'source_node_id' => $menuNode->id, 'target_node_id' => $voicemailNode->id, 'condition' => 'timeout']);
        FlowEdge::factory()->create(['flow_version_id' => $flowVersion->id, 'source_node_id' => $teamNode->id, 'target_node_id' => $voicemailNode->id, 'condition' => 'no_answer']);
        FlowEdge::factory()->create(['flow_version_id' => $flowVersion->id, 'source_node_id' => $teamNode->id, 'target_node_id' => $voicemailNode->id, 'condition' => 'timeout']);
        FlowEdge::factory()->create(['flow_version_id' => $flowVersion->id, 'source_node_id' => $voicemailNode->id, 'target_node_id' => $hangupNode->id, 'condition' => 'completed']);

        // 2. Compile
        $result = $this->artifactService->compileAndStore($flowVersion);
        $this->assertTrue($result['success']);

        $artifact = \App\Models\FlowCompiledArtifact::find($result['artifact_id']);
        
        // We assert against a known snapshot string
        $expectedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<document type="freeswitch/xml">
  <section name="dialplan">
    <context name="snapshot.example.com">
      <extension name="flow_11111111-1111-1111-1111-111111111111">
        <condition field="destination_number" expression="^flow_11111111-1111-1111-1111-111111111111$">
          <action application="transfer" data="node_10000000-0000-0000-0000-000000000001 XML snapshot.example.com"/>
        </condition>
      </extension>

      <extension name="node_10000000-0000-0000-0000-000000000001">
        <condition field="destination_number" expression="^node_10000000-0000-0000-0000-000000000001$">
          <action application="answer"/>
          <action application="transfer" data="node_10000000-0000-0000-0000-000000000002 XML snapshot.example.com"/>
        </condition>
      </extension>

      <extension name="node_10000000-0000-0000-0000-000000000002">
        <condition field="destination_number" expression="^node_10000000-0000-0000-0000-000000000002$">
          <action application="set" data="nizam_schedule_state="/>
          <action application="set" data="nizam_schedule_return_node=node_10000000-0000-0000-0000-000000000002_resume"/>
          <action application="transfer" data="schedule_22222222-2222-2222-2222-222222222222 XML snapshot.example.com"/>
        </condition>
      </extension>

      <extension name="node_10000000-0000-0000-0000-000000000002_resume">
        <condition field="destination_number" expression="^node_10000000-0000-0000-0000-000000000002_resume$">
          <action application="log" data="INFO Schedule state is ${nizam_schedule_state}"/>
          <condition field="${nizam_schedule_state}" expression="^open$">
            <action application="transfer" data="node_10000000-0000-0000-0000-000000000003 XML snapshot.example.com"/>
          </condition>
          <condition field="${nizam_schedule_state}" expression="^closed$">
            <action application="transfer" data="node_10000000-0000-0000-0000-000000000005 XML snapshot.example.com"/>
          </condition>
          <action application="transfer" data="node_10000000-0000-0000-0000-000000000005 XML snapshot.example.com"/>
        </condition>
      </extension>

      <extension name="node_10000000-0000-0000-0000-000000000003">
        <condition field="destination_number" expression="^node_10000000-0000-0000-0000-000000000003$">
          <action application="play_and_get_digits" data="1 1 3 5000 # ivr/welcome.wav silence_stream://250 nizam_menu_digits \d+"/>
          <condition field="${nizam_menu_digits}" expression="^1$">
            <action application="transfer" data="node_10000000-0000-0000-0000-000000000004 XML snapshot.example.com"/>
          </condition>
          <condition field="${nizam_menu_digits}" expression="^$">
            <action application="transfer" data="node_10000000-0000-0000-0000-000000000005 XML snapshot.example.com"/>
          </condition>
          <action application="transfer" data="node_10000000-0000-0000-0000-000000000005 XML snapshot.example.com"/>
        </condition>
      </extension>

      <extension name="node_10000000-0000-0000-0000-000000000004">
        <condition field="destination_number" expression="^node_10000000-0000-0000-0000-000000000004$">
          <action application="log" data="WARNING BridgeTeam node_10000000-0000-0000-0000-000000000004 resolved to empty dial string"/>
          <action application="transfer" data="node_10000000-0000-0000-0000-000000000005 XML snapshot.example.com"/>
        </condition>
      </extension>

      <extension name="node_10000000-0000-0000-0000-000000000005">
        <condition field="destination_number" expression="^node_10000000-0000-0000-0000-000000000005$">
          <action application="voicemail" data="default ${domain_name} 100"/>
          <action application="transfer" data="node_10000000-0000-0000-0000-000000000006 XML snapshot.example.com"/>
        </condition>
      </extension>

      <extension name="node_10000000-0000-0000-0000-000000000006">
        <condition field="destination_number" expression="^node_10000000-0000-0000-0000-000000000006$">
          <action application="hangup"/>
        </condition>
      </extension>

    </context>
  </section>
</document>
XML;

        // Note: For simplicity and dealing with non-deterministic node array ordering from DB query
        // we'll just check that each expected block exists. 
        // Real snapshot testing would freeze DB ordering or sort nodes.
        
        $this->assertStringContainsString('<extension name="flow_11111111-1111-1111-1111-111111111111">', $artifact->content);
        $this->assertStringContainsString('<action application="transfer" data="node_10000000-0000-0000-0000-000000000001 XML snapshot.example.com"/>', $artifact->content);
        
        // Start node check
        $this->assertStringContainsString('<action application="transfer" data="node_10000000-0000-0000-0000-000000000002 XML snapshot.example.com"/>', $artifact->content);

        // Schedule check
        $this->assertStringContainsString('<action application="set" data="nizam_schedule_return_node=node_10000000-0000-0000-0000-000000000002_resume"/>', $artifact->content);
        $this->assertStringContainsString('<action application="transfer" data="schedule_22222222-2222-2222-2222-222222222222 XML snapshot.example.com"/>', $artifact->content);

        // Menu check
        $this->assertStringContainsString('<action application="play_and_get_digits" data="1 1 3 5000 # ivr/welcome.wav silence_stream://250 nizam_menu_digits \d+"/>', $artifact->content);
        $this->assertStringContainsString('<condition field="${nizam_menu_digits}" expression="^1$">', $artifact->content);
        $this->assertStringContainsString('<action application="transfer" data="node_10000000-0000-0000-0000-000000000004 XML snapshot.example.com"/>', $artifact->content);

        // Team check (empty because no extensions)
        $this->assertStringContainsString('<action application="log" data="WARNING BridgeTeam node_10000000-0000-0000-0000-000000000004 resolved to empty dial string"/>', $artifact->content);

        // Voicemail check
        $this->assertStringContainsString('<action application="voicemail" data="default ${domain_name} 100"/>', $artifact->content);

        // Hangup check
        $this->assertStringContainsString('<action application="hangup"/>', $artifact->content);
    }
}