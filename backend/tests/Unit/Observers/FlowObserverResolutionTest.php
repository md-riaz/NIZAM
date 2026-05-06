<?php

namespace Tests\Unit\Observers;

use App\Models\Flow;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\Organization;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowObserverResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow_node_updated_triggers_manifest_rebuild_for_flow_organization(): void
    {
        $organization = Organization::factory()->create();
        $flow = Flow::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $flowVersion = FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'status' => 'draft',
            'definition_json' => [],
        ]);
        $node = FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
            'config_json' => [],
        ]);

        $builder = $this->mock(OrganizationManifestBuilder::class);
        $builder->shouldReceive('buildAndActivate')->once()->withArgs(fn ($arg) => $arg->is($organization));

        $node->update(['name' => 'Updated node']);
    }
}
