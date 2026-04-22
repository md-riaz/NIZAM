<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerSuspendedTest extends TestCase
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

    public function test_suspended_organization_returns_empty_directory(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_SUSPENDED,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDirectory($organization->domain);

        $this->assertStringContainsString('section name="directory"', $xml);
        $this->assertStringNotContainsString('1001', $xml);
    }

    public function test_suspended_organization_returns_empty_dialplan(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_SUSPENDED,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDialplan($organization->domain, '1001');

        $this->assertStringContainsString('section name="dialplan"', $xml);
        $this->assertStringNotContainsString('1001', $xml);
    }

    public function test_terminated_organization_returns_empty_directory(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_TERMINATED,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDirectory($organization->domain);

        $this->assertStringNotContainsString('1001', $xml);
    }

    public function test_active_organization_returns_valid_directory(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDirectory($organization->domain);

        $this->assertStringContainsString('1001', $xml);
    }

    public function test_trial_organization_returns_valid_directory(): void
    {
        $organization = Organization::factory()->create([
            'is_active' => true,
            'status' => Organization::STATUS_TRIAL,
        ]);

        $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDirectory($organization->domain);

        $this->assertStringContainsString('1001', $xml);
    }
}
