<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerIsolationTest extends TestCase
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

    public function test_concurrent_call_limit_included_in_did_routing(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
            'max_concurrent_calls' => 10,
        ]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $organization->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '+15551234567');

        $this->assertStringContainsString('application="limit"', $xml);
        $this->assertStringContainsString('organization_calls 10', $xml);
        $this->assertStringContainsString('NORMAL_TEMPORARY_FAILURE', $xml);
    }

    public function test_concurrent_call_limit_included_in_extension_routing(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
            'max_concurrent_calls' => 5,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '1001');

        $this->assertStringContainsString('application="limit"', $xml);
        $this->assertStringContainsString('organization_calls 5', $xml);
    }

    public function test_no_concurrent_call_limit_when_zero(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
            'max_concurrent_calls' => 0,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '1001');

        $this->assertStringNotContainsString('application="limit"', $xml);
        $this->assertStringContainsString('application="bridge"', $xml);
    }

    public function test_recording_path_uses_organization_id(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $compiler = new DialplanCompiler(
            app(\App\Services\Routing\NumberRoutingService::class),
            app(\App\Services\Routing\GatewayResolutionService::class),
            app(\App\Services\Routing\BridgeCompiler::class),
            app(\App\Services\Routing\RoutingGraphCompiler::class),
        );
        $method = new \ReflectionMethod($compiler, 'organizationRecordingPath');

        $path = $method->invoke($compiler, $organization);

        $this->assertStringContainsString($organization->id, $path);
        $this->assertStringContainsString('recordings', $path);
    }
}
