<?php

namespace Tests\Unit\Services;

use App\Models\Extension;
use App\Models\Tenant;
use App\Services\DialplanCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeConditionEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private DialplanCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new DialplanCompiler;
    }

    public function test_time_condition_generates_condition_with_wday_attribute(): void
    {
        $tenant = Tenant::create([
            'name' => 'TC Tenant',
            'domain' => 'tc.example.com',
            'slug' => 'tc-tenant',
            'is_active' => true,
        ]);

        $ext = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'pass123',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $tc = $tenant->timeConditions()->create([
            'name' => 'Weekday Hours',
            'conditions' => [
                ['wday' => '2-6', 'time_from' => '09:00', 'time_to' => '17:00'],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $ext->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $ext->id,
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15551112222',
            'destination_type' => 'time_condition',
            'destination_id' => $tc->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('tc.example.com', '+15551112222');

        // Should contain FreeSWITCH time-based condition attributes
        $this->assertStringContainsString('wday="2-6"', $xml);
        $this->assertStringContainsString('time-of-day="09:00-17:00"', $xml);
    }

    public function test_time_condition_generates_match_action(): void
    {
        $tenant = Tenant::create([
            'name' => 'TC Tenant',
            'domain' => 'tc.example.com',
            'slug' => 'tc-tenant',
            'is_active' => true,
        ]);

        $ext = $tenant->extensions()->create([
            'extension' => '2001',
            'password' => 'pass123',
            'directory_first_name' => 'Jane',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $tc = $tenant->timeConditions()->create([
            'name' => 'Business Hours',
            'conditions' => [
                ['wday' => '2-6', 'time_from' => '08:00', 'time_to' => '18:00'],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $ext->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $ext->id,
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15553334444',
            'destination_type' => 'time_condition',
            'destination_id' => $tc->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('tc.example.com', '+15553334444');

        // Match action: bridge to extension
        $this->assertStringContainsString('application="bridge"', $xml);
        $this->assertStringContainsString('user/2001@tc.example.com', $xml);
    }

    public function test_time_condition_generates_anti_action_for_no_match(): void
    {
        $tenant = Tenant::create([
            'name' => 'TC Tenant',
            'domain' => 'tc.example.com',
            'slug' => 'tc-tenant',
            'is_active' => true,
        ]);

        $ext = $tenant->extensions()->create([
            'extension' => '3001',
            'password' => 'pass123',
            'directory_first_name' => 'Bob',
            'directory_last_name' => 'Smith',
            'is_active' => true,
        ]);

        $tc = $tenant->timeConditions()->create([
            'name' => 'After Hours',
            'conditions' => [
                ['wday' => '2-6', 'time_from' => '09:00', 'time_to' => '17:00'],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $ext->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $ext->id,
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15555556666',
            'destination_type' => 'time_condition',
            'destination_id' => $tc->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('tc.example.com', '+15555556666');

        // No-match anti-action: voicemail
        $this->assertStringContainsString('anti-action', $xml);
        $this->assertStringContainsString('voicemail', $xml);
    }

    public function test_time_condition_with_only_wday(): void
    {
        $tenant = Tenant::create([
            'name' => 'TC Tenant',
            'domain' => 'tc.example.com',
            'slug' => 'tc-tenant',
            'is_active' => true,
        ]);

        $ext = $tenant->extensions()->create([
            'extension' => '4001',
            'password' => 'pass123',
            'directory_first_name' => 'Alice',
            'directory_last_name' => 'Jones',
            'is_active' => true,
        ]);

        $tc = $tenant->timeConditions()->create([
            'name' => 'Weekend Only',
            'conditions' => [
                ['wday' => '1,7'],
            ],
            'match_destination_type' => 'voicemail',
            'match_destination_id' => $ext->id,
            'no_match_destination_type' => 'extension',
            'no_match_destination_id' => $ext->id,
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15557778888',
            'destination_type' => 'time_condition',
            'destination_id' => $tc->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('tc.example.com', '+15557778888');

        $this->assertStringContainsString('wday="1,7"', $xml);
        $this->assertStringNotContainsString('time-of-day', $xml);
    }

    public function test_time_condition_without_conditions_routes_unconditionally(): void
    {
        $tenant = Tenant::create([
            'name' => 'TC Tenant',
            'domain' => 'tc.example.com',
            'slug' => 'tc-tenant',
            'is_active' => true,
        ]);

        $ext = $tenant->extensions()->create([
            'extension' => '5001',
            'password' => 'pass123',
            'directory_first_name' => 'Charlie',
            'directory_last_name' => 'Brown',
            'is_active' => true,
        ]);

        $tc = $tenant->timeConditions()->create([
            'name' => 'Always Active',
            'conditions' => [],
            'match_destination_type' => 'extension',
            'match_destination_id' => $ext->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $ext->id,
            'is_active' => true,
        ]);

        $did = $tenant->dids()->create([
            'number' => '+15559990000',
            'destination_type' => 'time_condition',
            'destination_id' => $tc->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('tc.example.com', '+15559990000');

        // Should bridge unconditionally (no time attributes means no anti-action)
        $this->assertStringContainsString('user/5001@tc.example.com', $xml);
        $this->assertStringNotContainsString('anti-action', $xml);
    }
}
