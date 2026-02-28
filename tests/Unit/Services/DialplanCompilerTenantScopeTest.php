<?php

namespace Tests\Unit\Services;

use App\Models\Extension;
use App\Models\Ivr;
use App\Models\Tenant;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private DialplanCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new DialplanCompiler;
    }

    public function test_did_routing_ignores_extension_from_other_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['is_active' => true, 'status' => Tenant::STATUS_ACTIVE]);
        $tenantB = Tenant::factory()->create(['is_active' => true, 'status' => Tenant::STATUS_ACTIVE]);

        // Extension belongs to tenant B
        $extB = $tenantB->extensions()->create([
            'extension' => '2001',
            'password' => 'secret',
            'is_active' => true,
            'directory_first_name' => 'Other',
            'directory_last_name' => 'Tenant',
        ]);

        // DID on tenant A references tenant B's extension ID (cross-tenant)
        $tenantA->dids()->create([
            'number' => '+15559999999',
            'destination_type' => 'extension',
            'destination_id' => $extB->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenantA->domain, '+15559999999');

        // Should NOT contain a bridge action since the extension doesn't belong to tenant A
        $this->assertStringNotContainsString('application="bridge"', $xml);
    }

    public function test_did_routing_uses_extension_from_same_tenant(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true, 'status' => Tenant::STATUS_ACTIVE]);

        $ext = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $tenant->dids()->create([
            'number' => '+15551111111',
            'destination_type' => 'extension',
            'destination_id' => $ext->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenant->domain, '+15551111111');

        $this->assertStringContainsString('application="bridge"', $xml);
        $this->assertStringContainsString('user/1001@', $xml);
    }

    public function test_did_routing_ignores_ivr_from_other_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['is_active' => true, 'status' => Tenant::STATUS_ACTIVE]);
        $tenantB = Tenant::factory()->create(['is_active' => true, 'status' => Tenant::STATUS_ACTIVE]);

        $ivrB = $tenantB->ivrs()->create([
            'name' => 'other-tenant-ivr',
            'greet_long' => 'welcome.wav',
            'greet_short' => 'short.wav',
            'timeout' => 5000,
            'options' => [],
        ]);

        $tenantA->dids()->create([
            'number' => '+15558888888',
            'destination_type' => 'ivr',
            'destination_id' => $ivrB->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan($tenantA->domain, '+15558888888');

        // Should NOT contain an ivr action since the IVR doesn't belong to tenant A
        $this->assertStringNotContainsString('application="ivr"', $xml);
    }
}
