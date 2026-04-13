<?php

namespace Tests\Unit\Services;

use App\Models\Flow;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\Tenant;
use App\Services\Routing\RoutingGraphCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingGraphCompilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_compiler_builds_deterministic_routing_graph_artifact(): void
    {
        $tenant = Tenant::factory()->create();
        $flow = Flow::factory()->create(['tenant_id' => $tenant->id]);
        $flowVersion = FlowVersion::factory()->create(['flow_id' => $flow->id]);

        $startNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
            'config_json' => ['announce' => 'welcome'],
        ]);
        $openNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'schedule_check',
            'config_json' => ['schedule_id' => 'schedule-1'],
        ]);
        $closedNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $openNode->id,
            'target_node_id' => $closedNode->id,
            'condition' => 'closed',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $openNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $openNode->id,
            'target_node_id' => $closedNode->id,
            'condition' => 'open',
        ]);

        $compiler = app(RoutingGraphCompiler::class);

        $first = $compiler->compile($flowVersion);
        $second = $compiler->compile($flowVersion->fresh());

        $this->assertSame($first['checksum'], $second['checksum']);
        $this->assertTrue($first['validation']['is_valid']);
        $this->assertSame('flow_'.$flow->id, data_get($first, 'entrypoint.extension'));
        $this->assertSame((string) $startNode->id, data_get($first, 'entrypoint.node_id'));
        $this->assertSame('next', data_get($first, 'edges.0.branch'));
    }

    public function test_compiler_reports_missing_required_branch_and_unreachable_node(): void
    {
        $tenant = Tenant::factory()->create();
        $flow = Flow::factory()->create(['tenant_id' => $tenant->id]);
        $flowVersion = FlowVersion::factory()->create(['flow_id' => $flow->id]);

        $startNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
        ]);
        $scheduleNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'schedule_check',
        ]);
        $orphanNode = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
        ]);

        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $scheduleNode->id,
            'condition' => 'next',
        ]);
        FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $scheduleNode->id,
            'target_node_id' => $startNode->id,
            'condition' => 'open',
        ]);

        $graph = app(RoutingGraphCompiler::class)->compile($flowVersion);

        $this->assertFalse($graph['validation']['is_valid']);
        $this->assertContains('missing_required_branch', array_column($graph['validation']['errors'], 'code'));
        $this->assertContains('unreachable_node', array_column($graph['validation']['warnings'], 'code'));
        $this->assertSame((string) $orphanNode->id, data_get(collect($graph['validation']['warnings'])->firstWhere('code', 'unreachable_node'), 'meta.node_id'));
    }
}
