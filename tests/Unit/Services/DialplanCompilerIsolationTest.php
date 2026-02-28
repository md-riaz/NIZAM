<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
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
        $this->compiler = new DialplanCompiler;
    }

    public function test_concurrent_call_limit_included_in_did_routing(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
            'max_concurrent_calls' => 10,
        ]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $tenant->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '+15551234567');

        $this->assertStringContainsString('application="limit"', $xml);
        $this->assertStringContainsString('tenant_calls 10', $xml);
        $this->assertStringContainsString('NORMAL_TEMPORARY_FAILURE', $xml);
    }

    public function test_concurrent_call_limit_included_in_extension_routing(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
            'max_concurrent_calls' => 5,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '1001');

        $this->assertStringContainsString('application="limit"', $xml);
        $this->assertStringContainsString('tenant_calls 5', $xml);
    }

    public function test_no_concurrent_call_limit_when_zero(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
            'max_concurrent_calls' => 0,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '1001');

        $this->assertStringNotContainsString('application="limit"', $xml);
        $this->assertStringContainsString('application="bridge"', $xml);
    }

    public function test_recording_path_uses_tenant_id(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $compiler = new DialplanCompiler;
        $method = new \ReflectionMethod($compiler, 'tenantRecordingPath');

        $path = $method->invoke($compiler, $tenant);

        $this->assertStringContainsString($tenant->id, $path);
        $this->assertStringContainsString('recordings', $path);
    }
}
