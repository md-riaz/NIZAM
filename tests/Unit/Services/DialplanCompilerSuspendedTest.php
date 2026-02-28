<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
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
        $this->compiler = new DialplanCompiler;
    }

    public function test_suspended_tenant_returns_empty_directory(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_SUSPENDED,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDirectory($tenant->domain);

        $this->assertStringContains('section name="directory"', $xml);
        $this->assertStringNotContainsString('1001', $xml);
    }

    public function test_suspended_tenant_returns_empty_dialplan(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_SUSPENDED,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '1001');

        $this->assertStringContains('section name="dialplan"', $xml);
        $this->assertStringNotContainsString('1001', $xml);
    }

    public function test_terminated_tenant_returns_empty_directory(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_TERMINATED,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDirectory($tenant->domain);

        $this->assertStringNotContainsString('1001', $xml);
    }

    public function test_active_tenant_returns_valid_directory(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDirectory($tenant->domain);

        $this->assertStringContainsString('1001', $xml);
    }

    public function test_trial_tenant_returns_valid_directory(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_TRIAL,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDirectory($tenant->domain);

        $this->assertStringContainsString('1001', $xml);
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
