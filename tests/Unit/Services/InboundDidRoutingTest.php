<?php

namespace Tests\Unit\Services;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Services\DialplanCompiler;
use App\Services\Routing\NumberRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundDidRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_did_resolution_matches_exact_number(): void
    {
        $tenant = Tenant::factory()->create(['settings' => ['default_country_code' => '1']]);
        $did = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15551234567',
            'is_active' => true,
        ]);

        $service = app(NumberRoutingService::class);
        $resolved = $service->resolveInboundDid($tenant, '+15551234567');

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($did));
    }

    public function test_generic_did_resolution_matches_normalized_number(): void
    {
        $tenant = Tenant::factory()->create(['settings' => ['default_country_code' => '1']]);
        $did = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15551234567',
            'is_active' => true,
        ]);

        $service = app(NumberRoutingService::class);
        $resolved = $service->resolveInboundDid($tenant, '5551234567');

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($did));
    }

    public function test_gateway_specific_did_resolution_works_when_only_gateway_scoped_route_exists(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create(['tenant_id' => $tenant->id]);

        $gatewaySpecific = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15551234567',
            'gateway_id' => $gateway->id,
            'is_active' => true,
        ]);

        $service = app(NumberRoutingService::class);
        $resolved = $service->resolveInboundDid($tenant, '+15551234567', $gateway);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($gatewaySpecific));
    }

    public function test_layered_precedence_generic_vs_gateway_specific(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create(['tenant_id' => $tenant->id]);

        $generic = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15551234567',
            'gateway_id' => null,
            'is_active' => true,
        ]);

        $gatewaySpecific = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15551234567',
            'gateway_id' => $gateway->id,
            'is_active' => true,
        ]);

        $service = app(NumberRoutingService::class);
        $resolved = $service->resolveInboundDid($tenant, '+15551234567', $gateway);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($gatewaySpecific));
        $this->assertFalse($resolved->is($generic));
    }

    public function test_flow_did_routes_to_compiled_local_transfer(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'test.example.com']);
        $flow = $tenant->flows()->create([
            'name' => 'Inbound Flow',
            'definition_json' => ['nodes' => [], 'edges' => []],
            'is_active' => true,
        ]);

        $tenant->dids()->create([
            'number' => '+15550001111',
            'destination_type' => 'flow',
            'destination_id' => $flow->id,
            'is_active' => true,
        ]);

        $compiler = app(DialplanCompiler::class);
        $xml = $compiler->compileDialplan('test.example.com', '+15550001111');

        $this->assertStringContainsString('transfer', $xml);
        $this->assertStringContainsString('flow_'.$flow->id.' XML test.example.com', $xml);
        $this->assertStringNotContainsString('park', $xml);
    }

    public function test_did_morph_map_resolves_ring_group_and_time_condition_destinations(): void
    {
        $tenant = Tenant::factory()->create();
        $extension = Extension::factory()->create(['tenant_id' => $tenant->id]);
        $ringGroup = $tenant->ringGroups()->create([
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'ring_timeout' => 20,
            'members' => [$extension->id],
            'is_active' => true,
        ]);
        $timeCondition = $tenant->timeConditions()->create([
            'name' => 'Business Hours',
            'conditions' => [],
            'match_destination_type' => 'extension',
            'match_destination_id' => $extension->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $ringDid = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
        ]);
        $timeDid = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'destination_type' => 'time_condition',
            'destination_id' => $timeCondition->id,
        ]);

        $this->assertInstanceOf(\App\Models\RingGroup::class, $ringDid->destination);
        $this->assertInstanceOf(\App\Models\TimeCondition::class, $timeDid->destination);
    }
}
