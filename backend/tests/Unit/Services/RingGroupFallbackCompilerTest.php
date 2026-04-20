<?php

namespace Tests\Unit\Services;

use App\Models\Bridge;
use App\Models\Extension;
use App\Models\Flow;
use App\Models\RingGroup;
use App\Models\Organization;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RingGroupFallbackCompilerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_ring_group_compiles_bridge_fallback(): void
    {
        $organization = Organization::factory()->create(['domain' => 'organization.example.com', 'is_active' => true]);
        $member = Extension::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $gateway = $organization->gateways()->create([
            'name' => 'Carrier A',
            'host' => 'sip.carrier.test',
            'port' => 5060,
            'transport' => 'udp',
            'register' => false,
            'profile' => 'external',
            'is_active' => true,
        ]);
        $bridge = Bridge::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'destination_template' => '+15551234567',
            'bridge_type' => 'gateway',
            'is_active' => true,
        ]);
        $ringGroup = RingGroup::factory()->create([
            'organization_id' => $organization->id,
            'members' => [$member->id],
            'fallback_destination_type' => 'bridge',
            'fallback_destination_id' => $bridge->id,
        ]);
        $organization->dids()->create([
            'number' => '+15550003333',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan($organization->domain, '+15550003333');

        $this->assertStringContainsString('nizam_delivery_target_type=ring_group', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$ringGroup->id, $xml);
        $this->assertStringContainsString('${originate_disposition}', $xml);
        $this->assertStringContainsString('sofia/gateway/v_'.$gateway->id.'/+15551234567', $xml);
    }

    public function test_ring_group_compiles_flow_fallback(): void
    {
        $organization = Organization::factory()->create(['domain' => 'organization.example.com', 'is_active' => true]);
        $member = Extension::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $flow = Flow::factory()->create(['organization_id' => $organization->id]);
        $ringGroup = RingGroup::factory()->create([
            'organization_id' => $organization->id,
            'members' => [$member->id],
            'fallback_destination_type' => 'flow',
            'fallback_destination_id' => $flow->id,
        ]);
        $organization->dids()->create([
            'number' => '+15550004444',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan($organization->domain, '+15550004444');

        $this->assertStringContainsString('flow_'.$flow->id.' XML '.$organization->domain, $xml);
    }

    public function test_ring_group_with_no_members_uses_fallback_directly(): void
    {
        $organization = Organization::factory()->create(['domain' => 'organization.example.com', 'is_active' => true]);
        $extension = Extension::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $ringGroup = RingGroup::factory()->create([
            'organization_id' => $organization->id,
            'members' => [],
            'fallback_destination_type' => 'voicemail',
            'fallback_destination_id' => $extension->id,
        ]);
        $organization->dids()->create([
            'number' => '+15550005555',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDialplan($organization->domain, '+15550005555');

        $this->assertStringContainsString('voicemail', $xml);
        $this->assertStringNotContainsString('application="bridge" data="user/', $xml);
    }
}
