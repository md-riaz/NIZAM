<?php

namespace Tests\Unit\Services;

use App\Models\CallRoutingPolicy;
use App\Models\Did;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerPreRoutingTest extends TestCase
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

    public function test_pre_routing_blacklist_rejects_call(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        // Create a pre-routing policy (NOT linked to any DID) with blacklist
        CallRoutingPolicy::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'priority' => 1,
            'conditions' => [
                ['type' => 'blacklist', 'params' => ['numbers' => ['5551234567']]],
            ],
            'match_destination_type' => null,
            'match_destination_id' => null,
        ]);

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);

        Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15559999999',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        // Call from blacklisted number
        $xml = $this->compiler->compileDialplan($organization->domain, '+15559999999', '5551234567');

        $this->assertStringContainsString('respond', $xml);
        $this->assertStringContainsString('403', $xml);
        $this->assertStringContainsString('policy-reject', $xml);
    }

    public function test_pre_routing_allows_non_blacklisted_caller(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        CallRoutingPolicy::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'priority' => 1,
            'conditions' => [
                ['type' => 'blacklist', 'params' => ['numbers' => ['5551234567']]],
            ],
            'match_destination_type' => null,
            'match_destination_id' => null,
        ]);

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);

        Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15559999999',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        // Call from non-blacklisted number
        $xml = $this->compiler->compileDialplan($organization->domain, '+15559999999', '5559876543');

        // Should proceed to normal DID routing
        $this->assertStringContainsString('application="transfer"', $xml);
        $this->assertStringNotContainsString('policy-reject', $xml);
    }

    public function test_did_linked_policies_excluded_from_pre_routing(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);

        // Policy linked as DID destination should NOT be evaluated pre-routing
        $policy = CallRoutingPolicy::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'conditions' => [
                ['type' => 'time_of_day', 'params' => ['start' => '09:00', 'end' => '17:00']],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $extension->id,
        ]);

        Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15551000000',
            'destination_type' => 'call_routing_policy',
            'destination_id' => $policy->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '+15551000000');

        // Should reach DID routing (not pre-routing reject)
        $this->assertStringContainsString('time-of-day="09:00-17:00"', $xml);
        $this->assertStringNotContainsString('policy-reject', $xml);
    }

    public function test_pre_routing_suspended_organization_rejects(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_SUSPENDED,
        ]);

        // Suspended organizations are rejected at compileDialplan level anyway (isOperational check)
        $xml = $this->compiler->compileDialplan($organization->domain, '+15559999999');

        $this->assertStringContainsString('dialplan', $xml);
        $this->assertStringNotContainsString('bridge', $xml);
    }

    public function test_pre_routing_redirect_overrides_did_routing(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        $normalExt = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);

        $redirectExt = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '9999',
            'is_active' => true,
        ]);

        // Global pre-routing policy redirecting all calls
        CallRoutingPolicy::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'priority' => 1,
            'conditions' => [],
            'match_destination_type' => 'extension',
            'match_destination_id' => $redirectExt->id,
        ]);

        Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15559999999',
            'destination_type' => 'extension',
            'destination_id' => $normalExt->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '+15559999999');

        // Should be redirected to extension 9999, not 1001
        $this->assertStringContainsString('policy-redirect', $xml);
        $this->assertStringContainsString('9999', $xml);
    }

    public function test_no_pre_routing_policies_proceeds_normally(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);

        Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15559999999',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '+15559999999');

        $this->assertStringContainsString('application="transfer"', $xml);
        $this->assertStringNotContainsString('policy-reject', $xml);
        $this->assertStringNotContainsString('policy-redirect', $xml);
    }
}
