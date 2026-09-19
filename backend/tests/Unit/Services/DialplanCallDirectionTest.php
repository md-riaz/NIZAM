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
     * The declaration has to precede the routing actions.
     *
     * The route ends in a `transfer`, which ends dialplan execution on this
     * extension — anything after it never runs. Asserting only that the
     * declaration sits inside the condition would still pass if it drifted below
     * the transfer, leaving the channel with no direction to report.
     */
    public function test_the_declaration_comes_before_the_transfer(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);

        $xml = app(DialplanCompiler::class)->compileLocalExtension($organization, $extension);

        $declaration = strpos($xml, 'call_direction=local');
        $condition = strpos($xml, '<condition');
        $transfer = strpos($xml, 'application="transfer"');

        $this->assertNotFalse($declaration);
        // Unguarded on purpose. The route always hands off with a transfer, so
        // if one stops being emitted the ordering this test exists to protect
        // has stopped being checkable — that should fail here, not pass
        // quietly.
        $this->assertNotFalse($transfer, 'The route no longer ends in a transfer.');
        $this->assertGreaterThan($condition, $declaration, 'The declaration is outside the matched condition.');
        $this->assertLessThan(
            $transfer,
            $declaration,
            'The declaration comes after the transfer, so it never runs.'
        );
    }

    /**
     * The same ordering on the inbound route, which reaches the same handoff.
     */
    public function test_the_inbound_declaration_comes_before_the_transfer(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = app(DialplanCompiler::class)->compileDidExtension($organization, $did);

        $declaration = strpos($xml, 'call_direction=inbound');
        $condition = strpos($xml, '<condition');
        $transfer = strpos($xml, 'application="transfer"');

        $this->assertNotFalse($declaration);
        $this->assertNotFalse($transfer, 'The route no longer ends in a transfer.');
        $this->assertGreaterThan($condition, $declaration, 'The declaration is outside the matched condition.');
        $this->assertLessThan(
            $transfer,
            $declaration,
            'The declaration comes after the transfer, so it never runs.'
        );
    }
}
