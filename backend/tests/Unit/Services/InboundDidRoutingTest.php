<?php

namespace Tests\Unit\Services;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Organization;
use App\Services\DialplanCompiler;
use App\Services\Routing\NumberRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundDidRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_generic_did_resolution_matches_exact_number(): void
    {
        $organization = Organization::factory()->create(['settings' => ['default_country_code' => '1']]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15551234567',
            'is_active' => true,
        ]);

        $service = app(NumberRoutingService::class);
        $resolved = $service->resolveInboundDid($organization, '+15551234567');

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($did));
    }

    public function test_generic_did_resolution_matches_normalized_number(): void
    {
        $organization = Organization::factory()->create(['settings' => ['default_country_code' => '1']]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15551234567',
            'is_active' => true,
        ]);

        $service = app(NumberRoutingService::class);
        $resolved = $service->resolveInboundDid($organization, '5551234567');

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($did));
    }

    public function test_gateway_specific_did_resolution_works_when_only_gateway_scoped_route_exists(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create(['organization_id' => $organization->id]);

        $gatewaySpecific = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15551234567',
            'gateway_id' => $gateway->id,
            'is_active' => true,
        ]);

        $service = app(NumberRoutingService::class);
        $resolved = $service->resolveInboundDid($organization, '+15551234567', $gateway);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($gatewaySpecific));
    }

    public function test_layered_precedence_generic_vs_gateway_specific(): void
    {
        $organization = Organization::factory()->create();
        $gateway = Gateway::factory()->create(['organization_id' => $organization->id]);

        $generic = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15551234567',
            'gateway_id' => null,
            'is_active' => true,
        ]);

        $gatewaySpecific = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15551234567',
            'gateway_id' => $gateway->id,
            'is_active' => true,
        ]);

        $service = app(NumberRoutingService::class);
        $resolved = $service->resolveInboundDid($organization, '+15551234567', $gateway);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($gatewaySpecific));
        $this->assertFalse($resolved->is($generic));
    }

    public function test_flow_did_routes_to_compiled_local_transfer(): void
    {
        $organization = Organization::factory()->create(['domain' => 'test.example.com']);
        $flow = \App\Models\Flow::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Inbound Flow',
        ]);

        $flowVersion = \App\Models\FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'status' => 'published',
        ]);

        $startNode = \App\Models\FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
        ]);

        $hangupNode = \App\Models\FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
        ]);

        \App\Models\FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'next',
        ]);

        $flow->update(['active_version_id' => $flowVersion->id]);

        $organization->dids()->create([
            'number' => '+15550001111',
            'destination_type' => 'flow',
            'destination_id' => $flow->id,
            'is_active' => true,
        ]);

        $compiler = app(DialplanCompiler::class);
        $xml = $compiler->compileDialplan('test.example.com', '+15550001111');

        $this->assertStringContainsString('nizam_entrypoint_route_type=flow', $xml);
        $this->assertStringContainsString('flow_'.$flow->id.' XML test.example.com', $xml);
        $this->assertStringNotContainsString('park', $xml);

        $artifact = \App\Models\FlowCompiledArtifact::where('flow_version_id', $flowVersion->id)
            ->where('artifact_type', \App\Models\FlowCompiledArtifact::ARTIFACT_TYPE_ROUTING_GRAPH)
            ->first();

        $this->assertNotNull($artifact);
    }

    public function test_preset_entrypoint_resolution_uses_did_scoped_preset_extension(): void
    {
        $organization = Organization::factory()->create(['domain' => 'preset.example.com']);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);

        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15550002222',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $compiler = app(DialplanCompiler::class);
        $xml = $compiler->compileDialplan('preset.example.com', '+15550002222');

        $this->assertStringContainsString('did_preset_'.preg_quote($did->id, '/'), $xml);
        $this->assertStringContainsString('nizam_entrypoint_route_type=preset', $xml);
        $this->assertStringContainsString('nizam_entrypoint_destination_type=extension', $xml);
        $this->assertStringContainsString('call_delivery_entrypoint XML preset.example.com', $xml);
    }

    public function test_preset_entrypoint_number_resolves_to_destination_actions(): void
    {
        $organization = Organization::factory()->create(['domain' => 'preset-entry.example.com']);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);

        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15550003333',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $compiler = app(DialplanCompiler::class);
        $xml = $compiler->compileDialplan('preset-entry.example.com', 'did_preset_'.$did->id);

        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$extension->id, $xml);
        $this->assertStringContainsString('call_delivery_entrypoint XML preset-entry.example.com', $xml);
    }

    public function test_compiled_flow_entrypoint_number_resolves_to_flow_transfer(): void
    {
        $organization = Organization::factory()->create(['domain' => 'flow-entry.example.com']);
        $flow = \App\Models\Flow::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Entrypoint Flow',
        ]);

        $flowVersion = \App\Models\FlowVersion::factory()->create([
            'flow_id' => $flow->id,
            'status' => 'published',
        ]);

        $startNode = \App\Models\FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'start',
        ]);

        $hangupNode = \App\Models\FlowNode::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'type' => 'hangup',
        ]);

        \App\Models\FlowEdge::factory()->create([
            'flow_version_id' => $flowVersion->id,
            'source_node_id' => $startNode->id,
            'target_node_id' => $hangupNode->id,
            'condition' => 'next',
        ]);

        $flow->update(['active_version_id' => $flowVersion->id]);

        $compiler = app(DialplanCompiler::class);
        $entryDialplan = $compiler->compileDialplan('flow-entry.example.com', 'flow_'.$flow->id);

        $this->assertStringContainsString('nizam_entrypoint_route_type=flow', $entryDialplan);
        $this->assertStringContainsString('flow_'.$flow->id.' XML flow-entry.example.com', $entryDialplan);
    }

    public function test_did_morph_map_resolves_ring_group_and_time_condition_destinations(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $ringGroup = $organization->ringGroups()->create([
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'ring_timeout' => 20,
            'members' => [$extension->id],
            'is_active' => true,
        ]);
        $timeCondition = $organization->timeConditions()->create([
            'name' => 'Business Hours',
            'conditions' => [],
            'match_destination_type' => 'extension',
            'match_destination_id' => $extension->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $ringDid = Did::factory()->create([
            'organization_id' => $organization->id,
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
        ]);
        $timeDid = Did::factory()->create([
            'organization_id' => $organization->id,
            'destination_type' => 'time_condition',
            'destination_id' => $timeCondition->id,
        ]);

        $this->assertInstanceOf(\App\Models\RingGroup::class, $ringDid->destination);
        $this->assertInstanceOf(\App\Models\TimeCondition::class, $timeDid->destination);
    }
}
