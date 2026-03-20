<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\RingGroup;
use App\Models\Tenant;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RingGroupFallbackCompilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ring_group_compiles_bridge_fallback(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'tenant.example.com', 'is_active' => true]);
        $member = Extension::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $gateway = $tenant->gateways()->create([
            'name' => 'Carrier A',
            'host' => 'sip.carrier.test',
            'port' => 5060,
            'transport' => 'udp',
            'register' => false,
            'profile' => 'external',
            'is_active' => true,
        ]);
        $bridge = Bridge::factory()->create([
            'tenant_id' => $tenant->id,
            'gateway_id' => $gateway->id,
            'destination_template' => '+15551234567',
            'bridge_type' => 'gateway',
            'is_active' => true,
        ]);
        $ringGroup = RingGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'members' => [$member->id],
            'fallback_destination_type' => 'bridge',
            'fallback_destination_id' => $bridge->id,
        ]);
        $tenant->dids()->create([
            'number' => '+15550003333',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan($tenant->domain, '+15550003333');

        $this->assertStringContainsString('continue_on_fail=USER_BUSY,NO_ANSWER,NO_USER_RESPONSE,ALLOTTED_TIMEOUT,NO_ROUTE_DESTINATION,UNALLOCATED_NUMBER,SUBSCRIBER_ABSENT', $xml);
        $this->assertStringContainsString('hangup_after_bridge=false', $xml);
        $this->assertStringContainsString('${originate_disposition}', $xml);
        $this->assertStringContainsString('sofia/gateway/v_'.$gateway->id.'/+15551234567', $xml);
    }

    public function test_ring_group_compiles_flow_fallback(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'tenant.example.com', 'is_active' => true]);
        $member = Extension::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $flow = Flow::factory()->create(['tenant_id' => $tenant->id]);
        $ringGroup = RingGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'members' => [$member->id],
            'fallback_destination_type' => 'flow',
            'fallback_destination_id' => $flow->id,
        ]);
        $tenant->dids()->create([
            'number' => '+15550004444',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan($tenant->domain, '+15550004444');

        $this->assertStringContainsString('flow_'.$flow->id.' XML '.$tenant->domain, $xml);
    }

    public function test_ring_group_with_no_members_uses_fallback_directly(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'tenant.example.com', 'is_active' => true]);
        $extension = Extension::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $ringGroup = RingGroup::factory()->create([
            'tenant_id' => $tenant->id,
            'members' => [],
            'fallback_destination_type' => 'voicemail',
            'fallback_destination_id' => $extension->id,
        ]);
        $tenant->dids()->create([
            'number' => '+15550005555',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan($tenant->domain, '+15550005555');

        $this->assertStringContainsString('voicemail', $xml);
        $this->assertStringNotContainsString('application="bridge" data="user/', $xml);
    }
}
