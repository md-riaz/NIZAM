<?php

namespace Tests\Unit\Services;

use App\Models\Extension;
use App\Models\Organization;
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
        $this->compiler = new DialplanCompiler(
            app(\App\Services\Routing\NumberRoutingService::class),
            app(\App\Services\Routing\GatewayResolutionService::class),
            app(\App\Services\Routing\BridgeCompiler::class),
            app(\App\Services\Routing\RoutingGraphCompiler::class),
        );
    }

    public function test_time_condition_generates_condition_with_wday_attribute(): void
    {
        $organization = Organization::create([
            'name' => 'TC Organization',
            'domain' => 'tc.example.com',
            'is_active' => true,
        ]);

        $ext = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'pass123',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $tc = $organization->timeConditions()->create([
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

        $did = $organization->dids()->create([
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
        $organization = Organization::create([
            'name' => 'TC Organization',
            'domain' => 'tc.example.com',
            'is_active' => true,
        ]);

        $ext = $organization->extensions()->create([
            'extension' => '2001',
            'password' => 'pass123',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $tc = $organization->timeConditions()->create([
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

        $did = $organization->dids()->create([
            'number' => '+15553334444',
            'destination_type' => 'time_condition',
            'destination_id' => $tc->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('tc.example.com', '+15553334444');

        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$ext->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML tc.example.com"', $xml);
        $this->assertStringNotContainsString('user/2001@tc.example.com', $xml);
    }

    public function test_time_condition_generates_anti_action_for_no_match(): void
    {
        $organization = Organization::create([
            'name' => 'TC Organization',
            'domain' => 'tc.example.com',
            'is_active' => true,
        ]);

        $ext = $organization->extensions()->create([
            'extension' => '3001',
            'password' => 'pass123',
            'first_name' => 'Bob',
            'last_name' => 'Smith',
            'is_active' => true,
        ]);

        $tc = $organization->timeConditions()->create([
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

        $did = $organization->dids()->create([
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
        $organization = Organization::create([
            'name' => 'TC Organization',
            'domain' => 'tc.example.com',
            'is_active' => true,
        ]);

        $ext = $organization->extensions()->create([
            'extension' => '4001',
            'password' => 'pass123',
            'first_name' => 'Alice',
            'last_name' => 'Jones',
            'is_active' => true,
        ]);

        $tc = $organization->timeConditions()->create([
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

        $did = $organization->dids()->create([
            'number' => '+15557778888',
            'destination_type' => 'time_condition',
            'destination_id' => $tc->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('tc.example.com', '+15557778888');

        $this->assertStringContainsString('wday="1,7"', $xml);
        $this->assertStringNotContainsString('time-of-day', $xml);
        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$ext->id, $xml);
        $this->assertStringContainsString('<anti-action application="transfer" data="call_delivery_entrypoint XML tc.example.com"', $xml);
        $this->assertStringNotContainsString('user/4001@tc.example.com', $xml);
    }

    public function test_time_condition_without_conditions_routes_unconditionally(): void
    {
        $organization = Organization::create([
            'name' => 'TC Organization',
            'domain' => 'tc.example.com',
            'is_active' => true,
        ]);

        $ext = $organization->extensions()->create([
            'extension' => '5001',
            'password' => 'pass123',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'is_active' => true,
        ]);

        $tc = $organization->timeConditions()->create([
            'name' => 'Always Active',
            'conditions' => [],
            'match_destination_type' => 'extension',
            'match_destination_id' => $ext->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $ext->id,
            'is_active' => true,
        ]);

        $did = $organization->dids()->create([
            'number' => '+15559990000',
            'destination_type' => 'time_condition',
            'destination_id' => $tc->id,
            'is_active' => true,
        ]);

        $xml = $this->compiler->compileDialplan('tc.example.com', '+15559990000');

        $this->assertStringContainsString('nizam_delivery_target_type=extension', $xml);
        $this->assertStringContainsString('nizam_delivery_target_id='.$ext->id, $xml);
        $this->assertStringContainsString('application="transfer" data="call_delivery_entrypoint XML tc.example.com"', $xml);
        $this->assertStringNotContainsString('anti-action', $xml);
        $this->assertStringNotContainsString('user/5001@tc.example.com', $xml);
    }
}
