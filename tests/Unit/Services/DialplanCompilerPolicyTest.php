<?php

namespace Tests\Unit\Services;

use App\Models\CallFlow;
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
        $this->compiler = new DialplanCompiler;
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
        $this->assertStringContainsString('bridge', $xml);
        $this->assertStringContainsString($extension->extension, $xml);
    }

    public function test_compiles_did_routing_via_call_flow(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $flow = CallFlow::factory()->create([
            'tenant_id' => $tenant->id,
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'play_prompt',
                    'data' => ['file' => 'welcome.wav'],
                    'next' => 'bridge1',
                ],
                [
                    'id' => 'bridge1',
                    'type' => 'bridge',
                    'data' => ['destination_type' => 'extension', 'destination_id' => $extension->id],
                    'next' => null,
                ],
            ],
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15552000000',
            'destination_type' => 'call_flow',
            'destination_id' => $flow->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '+15552000000');

        $this->assertStringContainsString('playback', $xml);
        $this->assertStringContainsString('welcome.wav', $xml);
        $this->assertStringContainsString('bridge', $xml);
        $this->assertStringContainsString($extension->extension, $xml);
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

        $this->assertStringContainsString('<action application="bridge"', $xml);
        $this->assertStringContainsString('<anti-action application="bridge"', $xml);
        $this->assertStringContainsString($matchExt->extension, $xml);
        $this->assertStringContainsString($noMatchExt->extension, $xml);
    }

    public function test_call_flow_with_record_node(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $flow = CallFlow::factory()->create([
            'tenant_id' => $tenant->id,
            'nodes' => [
                [
                    'id' => 'rec',
                    'type' => 'record',
                    'data' => ['path' => '/recordings/${uuid}.wav'],
                    'next' => null,
                ],
            ],
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15554000000',
            'destination_type' => 'call_flow',
            'destination_id' => $flow->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '+15554000000');

        $this->assertStringContainsString('record', $xml);
        $this->assertStringContainsString('/recordings/${uuid}.wav', $xml);
    }

    public function test_call_flow_with_webhook_node(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $flow = CallFlow::factory()->create([
            'tenant_id' => $tenant->id,
            'nodes' => [
                [
                    'id' => 'hook',
                    'type' => 'webhook',
                    'data' => ['url' => 'https://example.com/hook'],
                    'next' => null,
                ],
            ],
        ]);

        Did::factory()->create([
            'tenant_id' => $tenant->id,
            'number' => '+15555000000',
            'destination_type' => 'call_flow',
            'destination_id' => $flow->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '+15555000000');

        $this->assertStringContainsString('curl', $xml);
        $this->assertStringContainsString('https://example.com/hook', $xml);
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
