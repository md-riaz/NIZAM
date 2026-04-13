<?php

namespace Tests\Unit\Services;

use App\Models\Flow;
use App\Models\FlowVersion;
use App\Models\CallRoutingPolicy;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DialplanCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new DialplanCompiler(
            app(\App\Services\Routing\NumberRoutingService::class),
            app(\App\Services\Routing\GatewayResolutionService::class),
            app(\App\Services\Routing\BridgeCompiler::class),
            app(\App\Services\Routing\RoutingGraphCompiler::class),
        );
    }

    public function test_compiles_did_routing_via_call_routing_policy(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'conditions' => [
                ['type' => 'time_of_day', 'params' => ['start' => '09:00', 'end' => '17:00']],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $extension->id,
            'no_match_destination_type' => null,
            'no_match_destination_id' => null,
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15551000000',
            'destination_type' => 'call_routing_policy',
            'destination_id' => $policy->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '+15551000000');

        $this->assertStringContainsString('time-of-day="09:00-17:00"', $xml);
        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$extension->id, $xml);
        $this->assertStringContainsString('call_delivery_entrypoint XML '.$tenant->domain, $xml);
        $this->assertStringNotContainsString('application="bridge" data="user/', $xml);
    }

    public function test_compiles_did_routing_via_call_flow(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $flow = Flow::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15552000000',
            'destination_type' => 'flow',
            'destination_id' => $flow->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '+15552000000');

        $this->assertStringContainsString('<action application="transfer"', $xml);
        $this->assertStringContainsString('data="flow_' . $flow->id . ' XML ' . $tenant->domain . '"', $xml);
    }

    public function test_policy_with_no_match_destination_generates_anti_action(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $matchExt = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $noMatchExt = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'conditions' => [
                ['type' => 'time_of_day', 'params' => ['start' => '09:00', 'end' => '17:00']],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $matchExt->id,
            'no_match_destination_type' => 'extension',
            'no_match_destination_id' => $noMatchExt->id,
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15553000000',
            'destination_type' => 'call_routing_policy',
            'destination_id' => $policy->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '+15553000000');

        $this->assertStringContainsString('<action application="set" data="nizam_delivery_target_type=extension"', $xml);
        $this->assertStringContainsString('<anti-action application="set" data="nizam_delivery_target_type=extension"', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$matchExt->id, $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$noMatchExt->id, $xml);
        $this->assertStringContainsString('call_delivery_entrypoint XML '.$tenant->domain, $xml);
        $this->assertStringNotContainsString('application="bridge" data="user/', $xml);
    }

    public function test_policy_with_day_of_week_condition(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'conditions' => [
                ['type' => 'day_of_week', 'params' => ['days' => ['mon', 'tue', 'wed', 'thu', 'fri']]],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $extension->id,
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15556000000',
            'destination_type' => 'call_routing_policy',
            'destination_id' => $policy->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '+15556000000');

        $this->assertStringContainsString('wday="mon,tue,wed,thu,fri"', $xml);
    }
}
