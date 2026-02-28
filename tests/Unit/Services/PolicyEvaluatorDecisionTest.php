<?php

namespace Tests\Unit\Services;

use App\Models\CallRoutingPolicy;
use App\Models\Tenant;
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

    public function test_evaluate_policy_rejects_suspended_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => Tenant::STATUS_SUSPENDED,
            'is_active' => true,
        ]);

        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'conditions' => [],
        ]);

        $result = $this->evaluator->evaluatePolicy($policy, ['tenant_id' => $tenant->id]);

        $this->assertEquals(PolicyEvaluator::DECISION_REJECT, $result['decision']);
        $this->assertEquals('Tenant is suspended or terminated.', $result['reason']);
    }

    public function test_evaluate_policy_returns_redirect_on_match(): void
    {
        $tenant = Tenant::factory()->create();
        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'conditions' => [],
            'match_destination_type' => 'extension',
            'match_destination_id' => 'some-id',
        ]);

        $result = $this->evaluator->evaluatePolicy($policy, ['tenant_id' => $tenant->id]);

        $this->assertEquals(PolicyEvaluator::DECISION_REDIRECT, $result['decision']);
        $this->assertEquals('extension', $result['redirect_to']['type']);
        $this->assertEquals('some-id', $result['redirect_to']['id']);
    }

    public function test_evaluate_policy_returns_redirect_on_no_match(): void
    {
        $tenant = Tenant::factory()->create();
        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'conditions' => [
                ['type' => 'time_of_day', 'params' => ['start' => '23:00', 'end' => '23:01']],
            ],
            'match_destination_type' => 'extension',
            'match_destination_id' => 'match-id',
            'no_match_destination_type' => 'voicemail',
            'no_match_destination_id' => 'nomatch-id',
        ]);

        $now = \Carbon\Carbon::parse('2024-01-01 10:00:00');
        $result = $this->evaluator->evaluatePolicy($policy, [
            'tenant_id' => $tenant->id,
            'now' => $now,
        ]);

        $this->assertEquals(PolicyEvaluator::DECISION_REDIRECT, $result['decision']);
        $this->assertEquals('voicemail', $result['redirect_to']['type']);
    }

    public function test_evaluate_policy_allows_operational_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'conditions' => [],
            'match_destination_type' => null,
            'match_destination_id' => null,
        ]);

        $result = $this->evaluator->evaluatePolicy($policy, ['tenant_id' => $tenant->id]);

        $this->assertEquals(PolicyEvaluator::DECISION_ALLOW, $result['decision']);
    }
}
