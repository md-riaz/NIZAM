<?php

namespace Tests\Unit\Services;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dialplan declares what kind of call it is routing.
 *
 * FreeSWITCH's own channel direction cannot answer this: every a-leg is
 * "inbound" whether the caller is a carrier or one of this organization's own
 * extensions. Only the dialplan knows which branch it took, and the call detail
 * record reads `call_direction` back out of the channel afterwards — so a route
 * that does not set it produces a record that cannot say what the call was.
 */
class DialplanCallDirectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_a_number_route_declares_the_call_inbound(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15550001111',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDidExtension($organization, $did);

        $this->assertStringContainsString('data="call_direction=inbound"', $xml);
    }

    public function test_an_extension_route_declares_the_call_local(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);

        $xml = app(DialplanCompiler::class)->compileLocalExtension($organization, $extension);

        $this->assertStringContainsString('data="call_direction=local"', $xml);
    }

    /**
     * The declaration has to precede the routing actions, or a route that
     * transfers away leaves the channel without it.
     */
    public function test_the_declaration_comes_before_the_routing_actions(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);

        $xml = app(DialplanCompiler::class)->compileLocalExtension($organization, $extension);

        $declaration = strpos($xml, 'call_direction=local');
        $condition = strpos($xml, '<condition');

        $this->assertNotFalse($declaration);
        $this->assertGreaterThan($condition, $declaration, 'The declaration is outside the matched condition.');
    }
}
