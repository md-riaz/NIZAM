<?php

namespace Tests\Feature\Api;

use App\Models\CallRoutingPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PolicyEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyEvaluationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
    }

    public function test_can_evaluate_policy_via_api(): void
    {
        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conditions' => [
                ['type' => 'blacklist', 'params' => ['numbers' => ['5551234567']]],
            ],
            'match_destination_type' => null,
            'match_destination_id' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/call-routing-policies/{$policy->id}/evaluate", [
                'caller_id' => '5551234567',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('decision.decision', PolicyEvaluator::DECISION_REJECT);
    }

    public function test_evaluate_returns_allow_for_non_blacklisted(): void
    {
        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conditions' => [
                ['type' => 'blacklist', 'params' => ['numbers' => ['5551234567']]],
            ],
            'match_destination_type' => null,
            'match_destination_id' => null,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/call-routing-policies/{$policy->id}/evaluate", [
                'caller_id' => '5559876543',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('decision.decision', PolicyEvaluator::DECISION_ALLOW);
    }

    public function test_evaluate_with_time_context(): void
    {
        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conditions' => [
                ['type' => 'time_of_day', 'params' => ['start' => '09:00', 'end' => '17:00']],
            ],
            'match_destination_type' => null,
            'match_destination_id' => null,
        ]);

        // Evaluate at noon — should match
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/call-routing-policies/{$policy->id}/evaluate", [
                'time' => '2026-01-15T12:00:00Z',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('decision.decision', PolicyEvaluator::DECISION_ALLOW);
    }

    public function test_evaluate_enforces_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $policy = CallRoutingPolicy::factory()->create([
            'tenant_id' => $otherTenant->id,
            'conditions' => [],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/call-routing-policies/{$policy->id}/evaluate");

        $response->assertStatus(404);
    }
}
