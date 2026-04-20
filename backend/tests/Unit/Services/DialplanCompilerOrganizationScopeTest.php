<?php

namespace Tests\Unit\Services;

use App\Models\Extension;
use App\Models\Ivr;
use App\Models\Organization;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerOrganizationScopeTest extends TestCase
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

    public function test_did_routing_ignores_extension_from_other_organization(): void
    {
        $organizationA = Organization::factory()->create(['is_active' => true, 'status' => Organization::STATUS_ACTIVE]);
        $organizationB = Organization::factory()->create(['is_active' => true, 'status' => Organization::STATUS_ACTIVE]);

        // Extension belongs to organization B
        $extB = $organizationB->extensions()->create([
            'extension' => '2001',
            'password' => 'secret',
            'is_active' => true,
            'directory_first_name' => 'Other',
            'directory_last_name' => 'Organization',
        ]);

        // DID on organization A references organization B's extension ID (cross-organization)
        $organizationA->dids()->create([
            'number' => '+15559999999',
            'destination_type' => 'extension',
            'destination_id' => $extB->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organizationA->domain, '+15559999999');

        // Should NOT contain a bridge action since the extension doesn't belong to organization A
        $this->assertStringNotContainsString('application="bridge"', $xml);
    }

    public function test_did_routing_uses_extension_from_same_organization(): void
    {
        $organization = Organization::factory()->create(['is_active' => true, 'status' => Organization::STATUS_ACTIVE]);

        $ext = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $organization->dids()->create([
            'number' => '+15551111111',
            'destination_type' => 'extension',
            'destination_id' => $ext->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '+15551111111');

        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$ext->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML '.$organization->domain.'"', $xml);
        $this->assertStringNotContainsString('application="bridge" data="user/1001@', $xml);
    }

    public function test_did_routing_ignores_ivr_from_other_organization(): void
    {
        $organizationA = Organization::factory()->create(['is_active' => true, 'status' => Organization::STATUS_ACTIVE]);
        $organizationB = Organization::factory()->create(['is_active' => true, 'status' => Organization::STATUS_ACTIVE]);

        $ivrB = $organizationB->ivrs()->create([
            'name' => 'other-organization-ivr',
            'greet_long' => 'welcome.wav',
            'greet_short' => 'short.wav',
            'timeout' => 5000,
            'options' => [],
        ]);

        $organizationA->dids()->create([
            'number' => '+15558888888',
            'destination_type' => 'ivr',
            'destination_id' => $ivrB->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organizationA->domain, '+15558888888');

        // Should NOT contain an ivr action since the IVR doesn't belong to organization A
        $this->assertStringNotContainsString('application="ivr"', $xml);
    }
}
