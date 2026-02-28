<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialplanCompilerExtendedTest extends TestCase
{
    use RefreshDatabase;

    private DialplanCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new DialplanCompiler;
    }

    public function test_compile_did_routing_to_extension(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15551234567');

        $this->assertStringContainsString('section name="dialplan"', $xml);
        $this->assertStringContainsString('user/1001@test.example.com', $xml);
        $this->assertStringContainsString('bridge', $xml);
    }

    public function test_compile_did_routing_to_ring_group_simultaneous(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $ext1 = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $ext2 = $tenant->extensions()->create([
            'extension' => '1002',
            'password' => 'secret1234',
            'directory_first_name' => 'Jane',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $ringGroup = $tenant->ringGroups()->create([
            'name' => 'Sales',
            'strategy' => 'simultaneous',
            'ring_timeout' => 30,
            'members' => [$ext1->id, $ext2->id],
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15551234567',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15551234567');

        $this->assertStringContainsString('call_timeout=30', $xml);
        // Simultaneous uses comma separator
        $this->assertStringContainsString('user/1001@test.example.com', $xml);
        $this->assertStringContainsString('user/1002@test.example.com', $xml);
    }

    public function test_compile_did_routing_to_ring_group_sequential(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $ext1 = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $ringGroup = $tenant->ringGroups()->create([
            'name' => 'Support',
            'strategy' => 'sequential',
            'ring_timeout' => 20,
            'members' => [$ext1->id],
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15559876543',
            'destination_type' => 'ring_group',
            'destination_id' => $ringGroup->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15559876543');

        $this->assertStringContainsString('call_timeout=20', $xml);
        $this->assertStringContainsString('user/1001@test.example.com', $xml);
    }

    public function test_compile_did_routing_to_ivr(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $ivr = $tenant->ivrs()->create([
            'name' => 'Main Menu',
            'timeout' => 5,
            'max_failures' => 3,
            'options' => [],
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15551111111',
            'destination_type' => 'ivr',
            'destination_id' => $ivr->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15551111111');

        $this->assertStringContainsString('ivr', $xml);
        $this->assertStringContainsString('Main Menu', $xml);
    }

    public function test_compile_did_routing_to_voicemail(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15552222222',
            'destination_type' => 'voicemail',
            'destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15552222222');

        $this->assertStringContainsString('voicemail', $xml);
        $this->assertStringContainsString('test.example.com', $xml);
        $this->assertStringContainsString('1001', $xml);
    }

    public function test_compile_did_routing_to_time_condition(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $timeCondition = $tenant->timeConditions()->create([
            'name' => 'Business Hours',
            'conditions' => [
                ['wday' => 'mon-fri', 'time_from' => '09:00', 'time_to' => '17:00'],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $extension->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $extension->id,
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15553333333',
            'destination_type' => 'time_condition',
            'destination_id' => $timeCondition->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15553333333');

        $this->assertStringContainsString('section name="dialplan"', $xml);
        $this->assertStringContainsString('user/1001@test.example.com', $xml);
    }

    public function test_compile_directory_with_voicemail_settings(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'voicemail_enabled' => true,
            'voicemail_pin' => '1234',
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDirectory('test.example.com');

        $this->assertStringContainsString('vm-password', $xml);
        $this->assertStringContainsString('vm-enabled', $xml);
        $this->assertStringContainsString('1234', $xml);
    }

    public function test_compile_directory_with_caller_id(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'effective_caller_id_name' => 'John Doe',
            'effective_caller_id_number' => '1001',
            'outbound_caller_id_name' => 'Company',
            'outbound_caller_id_number' => '+15551234567',
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDirectory('test.example.com');

        $this->assertStringContainsString('effective_caller_id_name', $xml);
        $this->assertStringContainsString('John Doe', $xml);
        $this->assertStringContainsString('outbound_caller_id_name', $xml);
        $this->assertStringContainsString('Company', $xml);
    }

    public function test_inactive_tenant_returns_empty_directory(): void
    {
        Tenant::create([
            'name' => 'Inactive Tenant',
            'domain' => 'inactive.example.com',
            'slug' => 'inactive-tenant',
            'is_active' => false,
        ]);

        $xml = $this->compiler->compileDirectory('inactive.example.com');

        $this->assertStringContainsString('section name="directory"', $xml);
        $this->assertStringNotContainsString('<user', $xml);
    }

    public function test_inactive_extension_excluded_from_directory(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'Active',
            'directory_last_name' => 'User',
            'is_active' => true,
        ]);

        $tenant->extensions()->create([
            'extension' => '1002',
            'password' => 'secret1234',
            'directory_first_name' => 'Inactive',
            'directory_last_name' => 'User',
            'is_active' => false,
        ]);

        $xml = $this->compiler->compileDirectory('test.example.com');

        $this->assertStringContainsString('id="1001"', $xml);
        $this->assertStringNotContainsString('id="1002"', $xml);
    }

    public function test_inactive_did_not_routed(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $tenant->dids()->create([
            'number' => '+15559999999',
            'destination_type' => 'extension',
            'destination_id' => $extension->id,
            'is_active' => false,
        ]);

        $xml = $this->compiler->compileDialplan('test.example.com', '+15559999999');

        $this->assertStringContainsString('section name="dialplan"', $xml);
        $this->assertStringNotContainsString('user/1001', $xml);
    }
}
