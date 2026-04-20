<?php

namespace Tests\Unit\Services;

use App\Models\CallRoutingPolicy;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\PolicyEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyEvaluatorDecisionTest extends TestCase
{
    use RefreshDatabase;

    private PolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new PolicyEvaluator;
    }

    public function test_evaluate_policy_returns_allow_when_no_conditions(): void
    {
        $policy = CallRoutingPolicy::factory()->create([
            'conditions' => [],
            'match_destination_type' => null,
            'match_destination_id' => null,
        ]);

        $result = $this->evaluator->evaluatePolicy($policy);

        $this->assertEquals(PolicyEvaluator::DECISION_ALLOW, $result['decision']);
    }

    public function test_evaluate_policy_rejects_blacklisted_caller(): void
    {
        $policy = CallRoutingPolicy::factory()->create([
            'conditions' => [
                ['type' => 'blacklist', 'params' => ['numbers' => ['5551234567']]],
            ],
        ]);

        $result = $this->evaluator->evaluatePolicy($policy, ['caller_id' => '5551234567']);

        $this->assertEquals(PolicyEvaluator::DECISION_REJECT, $result['decision']);
        $this->assertEquals('Caller is blacklisted.', $result['reason']);
    }

    public function test_evaluate_policy_rejects_suspended_organization(): void
    {
        $organization = Organization::factory()->create([
            'status' => Organization::STATUS_SUSPENDED,
            'is_active' => true,
        ]);

        $policy = CallRoutingPolicy::factory()->create([
            'organization_id' => $organization->id,
            'conditions' => [],
        ]);

        $result = $this->evaluator->evaluatePolicy($policy, ['organization_id' => $organization->id]);

        $this->assertEquals(PolicyEvaluator::DECISION_REJECT, $result['decision']);
        $this->assertEquals('Organization is suspended or terminated.', $result['reason']);
    }

    public function test_evaluate_policy_returns_redirect_on_match(): void
    {
        $organization = Organization::factory()->create();
        $extension = Extension::factory()->create(['organization_id' => $organization->id]);
        $policy = CallRoutingPolicy::factory()->create([
            'organization_id' => $organization->id,
            'conditions' => [],
            'match_destination_type' => 'extension',
            'match_destination_id' => $extension->id,
        ]);

        $result = $this->evaluator->evaluatePolicy($policy, ['organization_id' => $organization->id]);

        $this->assertEquals(PolicyEvaluator::DECISION_REDIRECT, $result['decision']);
        $this->assertEquals('extension', $result['redirect_to']['type']);
        $this->assertEquals($extension->id, $result['redirect_to']['id']);
    }

    public function test_evaluate_policy_returns_redirect_on_no_match(): void
    {
        $organization = Organization::factory()->create();
        $matchExtension = Extension::factory()->create(['organization_id' => $organization->id]);
        $voicemailExtension = Extension::factory()->create(['organization_id' => $organization->id]);
        $policy = CallRoutingPolicy::factory()->create([
            'organization_id' => $organization->id,
            'conditions' => [
                ['type' => 'time_of_day', 'params' => ['start' => '23:00', 'end' => '23:01']],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => $matchExtension->id,
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => $voicemailExtension->id,
        ]);

        $now = \Carbon\Carbon::parse('2024-01-01 10:00:00');
        $result = $this->evaluator->evaluatePolicy($policy, [
            'organization_id' => $organization->id,
            'now' => $now,
        ]);

        $this->assertEquals(PolicyEvaluator::DECISION_REDIRECT, $result['decision']);
        $this->assertEquals('voicemail', $result['redirect_to']['type']);
        $this->assertEquals($voicemailExtension->id, $result['redirect_to']['id']);
    }

    public function test_evaluate_policy_allows_operational_organization(): void
    {
        $organization = Organization::factory()->create([
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $policy = CallRoutingPolicy::factory()->create([
            'organization_id' => $organization->id,
            'conditions' => [],
            'match_destination_type' => null,
            'match_destination_id' => null,
        ]);

        $result = $this->evaluator->evaluatePolicy($policy, ['organization_id' => $organization->id]);

        $this->assertEquals(PolicyEvaluator::DECISION_ALLOW, $result['decision']);
    }
}
