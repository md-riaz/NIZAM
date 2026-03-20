<?php

namespace Tests\Unit\Services;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Gateway;
use App\Models\GatewayRegistration;
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

    public function test_gateway_registration_specific_did_resolution_works_when_only_registration_scoped_route_exists(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create(['tenant_id' => $tenant->id]);
        $registration = GatewayRegistration::create([
            'gateway_id' => $gateway->id,
            'registration_identifier' => 'reg-1',
            'username' => 'mockuser',
            'realm' => 'sip-mock.local',
            'proxy' => 'sip-mock:5070',
            'transport' => 'udp',
            'status' => 'REGED',
        ]);

        $registrationSpecific = Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15551234567',
            'gateway_id' => $gateway->id,
            'gateway_registration_id' => $registration->id,
            'is_active' => true,
        ]);

        $service = app(NumberRoutingService::class);
        $resolved = $service->resolveInboundDid($tenant, '+15551234567', $gateway, $registration);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($registrationSpecific));
    }

    public function test_same_did_number_cannot_currently_exist_as_generic_and_gateway_specific_route(): void
    {
        $this->markTestIncomplete('Current schema uses unique (tenant_id, number), so DID precedence across generic/gateway/registration variants cannot be fully represented yet. FusionPBX-style inbound route layering needs a schema change.');
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
        $this->assertStringContainsString('flow_'.$flow->id.' XML default', $xml);
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
