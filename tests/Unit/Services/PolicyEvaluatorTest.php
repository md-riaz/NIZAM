<?php

namespace Tests\Unit\Services;

use App\Models\CallRoutingPolicy;
use App\Services\PolicyEvaluator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PolicyEvaluatorTest extends TestCase
{
    private PolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new PolicyEvaluator;
    }

    public function test_empty_conditions_always_match(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [];

        $this->assertTrue($this->evaluator->evaluate($policy));
    }

    public function test_time_of_day_matches_within_range(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'time_of_day', 'params' => ['start' => '09:00', 'end' => '17:00']],
        ];

        $context = ['now' => Carbon::createFromFormat('H:i', '12:00')];
        $this->assertTrue($this->evaluator->evaluate($policy, $context));
    }

    public function test_time_of_day_does_not_match_outside_range(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'time_of_day', 'params' => ['start' => '09:00', 'end' => '17:00']],
        ];

        $context = ['now' => Carbon::createFromFormat('H:i', '20:00')];
        $this->assertFalse($this->evaluator->evaluate($policy, $context));
    }

    public function test_day_of_week_matches_correct_day(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'day_of_week', 'params' => ['days' => ['mon', 'tue', 'wed', 'thu', 'fri']]],
        ];

        // Wednesday
        $context = ['now' => Carbon::parse('2026-02-25')]; // Wednesday
        $this->assertTrue($this->evaluator->evaluate($policy, $context));
    }

    public function test_day_of_week_does_not_match_saturday(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'day_of_week', 'params' => ['days' => ['mon', 'tue', 'wed', 'thu', 'fri']]],
        ];

        // Saturday
        $context = ['now' => Carbon::parse('2026-02-28')]; // Saturday
        $this->assertFalse($this->evaluator->evaluate($policy, $context));
    }

    public function test_day_of_week_does_not_match_weekend(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'day_of_week', 'params' => ['days' => ['mon', 'tue', 'wed', 'thu', 'fri']]],
        ];

        // Sunday
        $context = ['now' => Carbon::parse('2026-03-01')]; // Sunday
        $this->assertFalse($this->evaluator->evaluate($policy, $context));
    }

    public function test_caller_id_pattern_matches(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'caller_id_pattern', 'params' => ['pattern' => '+1555*']],
        ];

        $context = ['caller_id' => '+15551234567'];
        $this->assertTrue($this->evaluator->evaluate($policy, $context));
    }

    public function test_caller_id_pattern_does_not_match(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'caller_id_pattern', 'params' => ['pattern' => '+1555*']],
        ];

        $context = ['caller_id' => '+14161234567'];
        $this->assertFalse($this->evaluator->evaluate($policy, $context));
    }

    public function test_blacklist_passes_when_not_in_list(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'blacklist', 'params' => ['numbers' => ['+15551111111', '+15552222222']]],
        ];

        $context = ['caller_id' => '+15553333333'];
        $this->assertTrue($this->evaluator->evaluate($policy, $context));
    }

    public function test_blacklist_fails_when_in_list(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'blacklist', 'params' => ['numbers' => ['+15551111111', '+15552222222']]],
        ];

        $context = ['caller_id' => '+15551111111'];
        $this->assertFalse($this->evaluator->evaluate($policy, $context));
    }

    public function test_geo_prefix_matches(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'geo_prefix', 'params' => ['prefixes' => ['+1416', '+1647']]],
        ];

        $context = ['caller_id' => '+14161234567'];
        $this->assertTrue($this->evaluator->evaluate($policy, $context));
    }

    public function test_geo_prefix_does_not_match(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'geo_prefix', 'params' => ['prefixes' => ['+1416', '+1647']]],
        ];

        $context = ['caller_id' => '+15551234567'];
        $this->assertFalse($this->evaluator->evaluate($policy, $context));
    }

    public function test_multiple_conditions_all_must_match(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'time_of_day', 'params' => ['start' => '09:00', 'end' => '17:00']],
            ['type' => 'caller_id_pattern', 'params' => ['pattern' => '+1555*']],
        ];

        // Both match
        $context = [
            'now' => Carbon::createFromFormat('H:i', '12:00'),
            'caller_id' => '+15551234567',
        ];
        $this->assertTrue($this->evaluator->evaluate($policy, $context));

        // Time matches but caller doesn't
        $context = [
            'now' => Carbon::createFromFormat('H:i', '12:00'),
            'caller_id' => '+14161234567',
        ];
        $this->assertFalse($this->evaluator->evaluate($policy, $context));
    }

    public function test_unknown_condition_type_returns_false(): void
    {
        $policy = new CallRoutingPolicy;
        $policy->conditions = [
            ['type' => 'unknown_type', 'params' => []],
        ];

        $this->assertFalse($this->evaluator->evaluate($policy));
    }
}
