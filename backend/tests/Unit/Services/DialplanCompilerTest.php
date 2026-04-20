<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerTest extends TestCase
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

    private function createOrganizationWithExtension(): array
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'effective_caller_id_name' => 'John Doe',
            'effective_caller_id_number' => '1001',
            'is_active' => true,
        ]);

        return [$organization, $extension];
    }

    public function test_compile_directory_returns_valid_xml_for_organization_with_extensions(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();

        $xml = $this->compiler->compileDirectory('test.example.com');

        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('<section name="directory">', $xml);
        $this->assertStringContainsString('<domain name="test.example.com">', $xml);
        $this->assertStringContainsString('id="1001"', $xml);
        $this->assertStringContainsString('value="secret1234"', $xml);
        $this->assertStringContainsString('effective_caller_id_name', $xml);
    }

    public function test_compile_directory_returns_empty_response_for_unknown_domain(): void
    {
        $xml = $this->compiler->compileDirectory('unknown.example.com');

        $this->assertStringContainsString('<section name="directory"></section>', $xml);
    }

    public function test_compile_dialplan_routes_to_extension_for_internal_calls(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();

        $xml = $this->compiler->compileDialplan('test.example.com', '1001');

        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('<section name="dialplan">', $xml);
        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$extension->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML test.example.com"', $xml);
        $this->assertStringNotContainsString('application="bridge" data="user/1001@test.example.com"', $xml);
    }

    public function test_compile_dialplan_returns_empty_response_for_unknown_domain(): void
    {
        $xml = $this->compiler->compileDialplan('unknown.example.com', '1001');

        $this->assertStringContainsString('<section name="dialplan"></section>', $xml);
    }

    public function test_compile_dialplan_returns_failsafe_for_unroutable_destination(): void
    {
        [$organization, $extension] = $this->createOrganizationWithExtension();

        $xml = $this->compiler->compileDialplan('test.example.com', '9999');

        $this->assertStringContainsString('<section name="dialplan">', $xml);
        $this->assertStringContainsString('failsafe', $xml);
        $this->assertStringContainsString('application="log"', $xml);
        $this->assertStringContainsString('application="respond"', $xml);
        $this->assertStringContainsString('404', $xml);
    }
}
