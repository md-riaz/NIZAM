<?php

namespace Tests\Unit\Models;

use App\Models\Alert;
use App\Models\AlertPolicy;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_alert_policy(): void
    {
        $tenant = Tenant::factory()->create();
        $policy = AlertPolicy::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('alert_policies', ['id' => $policy->id]);
    }

    public function test_valid_metrics(): void
    {
        $this->assertContains('abandon_rate', AlertPolicy::VALID_METRICS);
        $this->assertContains('webhook_failures', AlertPolicy::VALID_METRICS);
        $this->assertContains('gateway_flapping', AlertPolicy::VALID_METRICS);
        $this->assertContains('sla_drop', AlertPolicy::VALID_METRICS);
    }

    public function test_evaluate_condition_gt(): void
    {
        $policy = AlertPolicy::factory()->create([
            'condition' => AlertPolicy::CONDITION_GT,
            'threshold' => 50,
        ]);

        $this->assertTrue($policy->evaluateCondition(51));
        $this->assertFalse($policy->evaluateCondition(50));
        $this->assertFalse($policy->evaluateCondition(49));
    }

    public function test_evaluate_condition_lt(): void
    {
        $policy = AlertPolicy::factory()->create([
            'condition' => AlertPolicy::CONDITION_LT,
            'threshold' => 80,
        ]);

        $this->assertTrue($policy->evaluateCondition(79));
        $this->assertFalse($policy->evaluateCondition(80));
    }

    public function test_evaluate_condition_gte(): void
    {
        $policy = AlertPolicy::factory()->create([
            'condition' => AlertPolicy::CONDITION_GTE,
            'threshold' => 50,
        ]);

        $this->assertTrue($policy->evaluateCondition(50));
        $this->assertTrue($policy->evaluateCondition(51));
        $this->assertFalse($policy->evaluateCondition(49));
    }

    public function test_evaluate_condition_lte(): void
    {
        $policy = AlertPolicy::factory()->create([
            'condition' => AlertPolicy::CONDITION_LTE,
            'threshold' => 80,
        ]);

        $this->assertTrue($policy->evaluateCondition(80));
        $this->assertTrue($policy->evaluateCondition(79));
        $this->assertFalse($policy->evaluateCondition(81));
    }

    public function test_is_in_cooldown(): void
    {
        $policy = AlertPolicy::factory()->create([
            'cooldown_minutes' => 15,
            'last_triggered_at' => now(),
        ]);

        $this->assertTrue($policy->isInCooldown());
    }

    public function test_is_not_in_cooldown_when_expired(): void
    {
        $policy = AlertPolicy::factory()->create([
            'cooldown_minutes' => 15,
            'last_triggered_at' => now()->subMinutes(20),
        ]);

        $this->assertFalse($policy->isInCooldown());
    }

    public function test_is_not_in_cooldown_when_never_triggered(): void
    {
        $policy = AlertPolicy::factory()->create([
            'last_triggered_at' => null,
        ]);

        $this->assertFalse($policy->isInCooldown());
    }

    public function test_has_many_alerts(): void
    {
        $policy = AlertPolicy::factory()->create();
        Alert::factory()->count(3)->create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
        ]);

        $this->assertCount(3, $policy->alerts);
    }
}
